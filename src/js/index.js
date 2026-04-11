import QRCode from 'qrcode'
import { Calendar } from '@fullcalendar/core'
import dayGridPlugin from '@fullcalendar/daygrid'
import timeGridPlugin from '@fullcalendar/timegrid'
import iCalendarPlugin from '@fullcalendar/icalendar'
import listPlugin from '@fullcalendar/list';
import css from  '/src/css/layout.css'
import css1 from '/src/css/weather-icons-wind.min.css'
import css2 from '/src/css/weather-icons.min.css'
import scss from '/src/css/fonts.scss'



let originalPhotoSrc = '';
let clockTimer = null;
let calendarInstance = null;
let sunMoonFlags = { showsunrisesunset: true, showmoonphase: true };

// ── Scheduling helpers ────────────────────────────────────────────────────────

// Fires fn at the next occurrence of HH:MM, then every 24 h at that same time.
function scheduleDailyAt(hour, minute, fn) {
  function msUntilNext() {
    var now  = new Date();
    var next = new Date();
    next.setHours(hour, minute, 0, 0);
    if (next <= now) next.setDate(next.getDate() + 1);
    return next - now;
  }
  function tick() {
    fn();
    setTimeout(tick, msUntilNext());
  }
  setTimeout(tick, msUntilNext());
}

function initSchedule(cfg) {
  var defaults = {
    image_hour: 4,    image_minute: 0,
    word_hour:  4,    word_minute:  30,
    quote_hour: 4,    quote_minute: 30,
    weather_interval_min:  30,
    calendar_interval_min: 1,
    ski_interval_min:      30,
  };
  cfg = Object.assign({}, defaults, cfg || {});

  scheduleDailyAt(cfg.image_hour,  cfg.image_minute,  hardReloadCal);
  scheduleDailyAt(cfg.quote_hour,  cfg.quote_minute,  function() { loadQuote(true); });

  setInterval(fetchWeatherData, cfg.weather_interval_min  * 60000);
  setInterval(fetchCalData,     cfg.calendar_interval_min * 60000);
  setInterval(loadSkiData,      cfg.ski_interval_min      * 60000);
  setInterval(loadWord,  60000); // poll for admin-forced word changes
  setInterval(loadQuote, 60000); // poll for admin-forced quote changes

  setInterval(showHideCalendars, 1000); // GPIO — always 1 s
}

// ── Startup ───────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', function() {
  procImage()    // pre-process any new images in the background
  SelectImages() // pick background; calendar loads after image fires 'load'
  loadSkiData()
  loadQuote()
  loadWord()

  // Load schedule config then wire up all timers
  $.ajax({
    type: 'GET',
    url: 'schedule.json',
    dataType: 'json',
    success: function(cfg) { initSchedule(cfg); },
    error:   function()    { initSchedule({}); }
  });
})

window.addEventListener('resize', fitOverlayStack);

// Keyboard shortcuts: T = cycle view, R = full refresh with new image, S = calendar-only refresh.
document.addEventListener('keydown',function(evt) {
    if ( evt.keyCode ==84) //T
    {
        toggleMode(evt);
    }
    if ( evt.keyCode ==82) //R
    {
        reloadCal();
    }
    if ( evt.keyCode ==83) //S
    {
        reloadCal_No_Image_Change();
    }
    if ( evt.keyCode >=48 && evt.keyCode <58) {
        toggleCal(evt);
    }
});


// Wait for the photo to load before building the calendar so resizeCal() has correct dimensions.
$("#photo").bind('load', function() {
  console.log('Load Calendar')
  loadCal()
});


// Fetches available calendar files and display flags, then renders the appropriate view.
function loadCal()
{
  var initview = document.getElementById('currview').value
  $.ajax({
   type: "POST",
   url: 'loadCalsAndNotes.php',
   dataType: 'json',
   success: function(data)
   {
      if (data.validviews) {
        document.getElementById('validviews').value = data.validviews;
        // If current view is no longer valid, reset to first view
        var viewarr = data.validviews.split(',');
        if (!viewarr.includes(initview)) {
          initview = viewarr[0];
          document.getElementById('currview').value = initview;
        }
      }
      // Hide BottomBox before resizeCal so the calendar gets the correct height from the start
      if (data.showhourlyweather === false) {
        $('#BottomBox').hide();
      } else {
        $('#BottomBox').show();
      }
       var calHeight = resizeCal();
       if(initview=='timeGridWeek' || initview =='dayGridMonth')
       {
         makeCalendar(data.calendars, data.cal_languages);
       }else 
       {
         if(initview=='notes')
         {
            makeNotes();
         }else
         {
           makeRecipe();
         }
       }
   }
  });
}


// Restores the background photo after leaving recipe view.
function restorePhoto() {
  if (!$('body').hasClass('recipe-view')) return;
  if (originalPhotoSrc) $('#photo').attr('src', originalPhotoSrc);
  $('#photo').removeClass('photo-recipe');
  $('body').removeClass('recipe-view');
}

function makeNotes()
{
  restorePhoto();
  var calendarEl = document.getElementById('calendar')
  $.ajax({
   type: "POST",
   url: 'notes/note.html',
   dataType: 'html',
   success: function(data)
   {
      calendarEl.innerHTML = '<div class="rec-title">FAMILY NOTES<br></div><div id="fn-body">'+data+'</div>';

      $.ajax({
        type: 'GET',
        url: 'fetchNotesConfig.php',
        dataType: 'json',
        success: function(cfg) { renderNotesQR(cfg || {}); },
        error:   function()    { renderNotesQR({}); }
      });
   }
  });
  fetchWeatherData();
  fitOverlayStack();
  window.dispatchEvent(new Event('resize'));
}

function renderNotesQR(cfg)
{
  var showNotes = (cfg.show_notes_qr !== false); // default true
  var showWifi  = !!(cfg.show_wifi_qr && cfg.wifi_ssid);
  if (!showNotes && !showWifi) return;

  var qrOpts   = { width: 160, margin: 1, color: { dark: '#000', light: '#fff' } };
  var adminUrl = cfg.admin_url || (window.location.href.replace(/\/[^\/]*(\?.*)?$/, '/') + 'adminNotes.php');
  var promises = [];

  if (showNotes) {
    promises.push(
      QRCode.toDataURL(adminUrl, qrOpts)
        .then(function(d) { return makeQRItem(d, 'Edit Notes'); })
    );
  }
  if (showWifi) {
    var wifiStr = 'WIFI:T:WPA;S:' + cfg.wifi_ssid + ';P:' + cfg.wifi_password + ';;';
    promises.push(
      QRCode.toDataURL(wifiStr, qrOpts)
        .then(function(d) { return makeQRItem(d, 'Join WiFi'); })
    );
  }

  Promise.all(promises).then(function(items) {
    $('#fn-body').append('<div id="notes-qr">' + items.join('') + '</div>');
  });
}

function makeQRItem(dataUrl, label)
{
  return '<div class="qr-item">' +
    '<img src="' + dataUrl + '" width="160" height="160">' +
    '<div class="notes-qr-label">' + label + '</div>' +
  '</div>';
}
function makeRecipe()
{
  var calendarEl = document.getElementById('calendar')
  $.ajax({
   type: "POST",
   url: 'fetchRecipe.php',
   dataType: 'json',
   success: function(data)
   {
    if(data != null)
    {
      calendarEl.innerHTML = '<div class="rec-title">'+data.name+'<br></div><div id="fn-body"><small>'+data.description+'</small><div id="recipeIngContain"><ul id="recipeIngredients"></ul></div><div id="recipeStepsContain"><ol id="recipeSteps"></ol></div></div>';
      $('#photo').attr('src', 'http://192.168.6.122:9925/api/media/recipes/'+data.id+'/images/original.webp').addClass('photo-recipe');
      $('body').addClass('recipe-view');
      buildIngredients(data.recipeIngredient)
      buildSteps(data.recipeInstructions)
    }else
    {
      calendarEl.innerHTML = '<div class="rec-title">MEAL PLAN<br></div><div id="fn-body"><small>No recipe detected.<br>Go to Mealie and add to meal plan.</small></div>';
    }
   },
   error: function(xhr, status, err)
   {
    console.log('fetchRecipe error', status, err)
    calendarEl.innerHTML = '<div class="rec-title">MEAL PLAN<br></div><div id="fn-body"><small>No recipe detected.<br>Go to Mealie and add to meal plan.</small></div>';
   }
  });
  fetchWeatherData();
  window.dispatchEvent(new Event('resize'));
}

function loadQuote(force)
{
  $.ajax({
    type: "POST",
    url: 'fetchQuote.php',
    data: force ? { force: 1 } : {},
    dataType: 'json',
    success: function(data) {
      if (data && data.q) {
        $('#quote').show().html('"' + data.q + '"<span class="quote-author">— ' + data.a + '</span>');
      } else {
        $('#quote').hide().html('');
      }
      repositionSki();
    }
  });
}

function loadWord()
{
  $.ajax({
    type: "POST",
    url: 'fetchWord.php',
    dataType: 'json',
    success: function(data) {
      if (!data || !data.word) return;

      function phoneticSpan(p) { return p ? '<span class="word-phonetic">' + p + '</span>' : ''; }

      var enBox = '<div class="word-box word-box-en">' +
        '<span class="word-title">' + data.word + '</span>' + phoneticSpan(data.phonetic) +
        (data.definition_en ? '<div class="word-definition">' + data.definition_en + '</div>' : '') +
      '</div>';

      var frBox = '';
      if (data.show_fr && data.definition_fr) {
        frBox = '<div class="word-box word-box-fr">' +
          '<span class="word-title">' + (data.word_fr || data.word) + '</span>' + phoneticSpan(data.phonetic_fr) +
          '<div class="word-definition">' + data.definition_fr + '</div>' +
        '</div>';
      }

      var esBox = '';
      if (data.show_es && data.definition_es) {
        esBox = '<div class="word-box word-box-es">' +
          '<span class="word-title">' + (data.word_es || data.word) + '</span>' + phoneticSpan(data.phonetic_es) +
          '<div class="word-definition">' + data.definition_es + '</div>' +
        '</div>';
      }

      $('#word').html('<div class="word-row">' + enBox + frBox + esBox + '</div>');
      repositionSki();
    }
  });
}

function repositionSki()
{
  var $ski = $('#ski');
  if (!$ski.text().trim()) return;
  var $stack = $('#overlayStack');
  var stackBottom = $stack.position().top + $stack.outerHeight(true);
  $ski.css({ top: stackBottom, bottom: '', left: '0px', right: '' });
}

function buildIngredients(data)
{
  var ingredients = '';
  $.each(data, function(index, ing) {
      $('#recipeIngredients').append('<li>' + ing.display + '</li>');
  });
  return ingredients
}
function buildSteps(data)
{
  var steps = '';
  $.each(data, function(index, step) {
      $('#recipeSteps').append('<li>' + step.text + '</li>');
  });
  return steps
}



// Initialises FullCalendar with the provided .ics sources and a randomly chosen locale.
function makeCalendar(data, languages)
{
  restorePhoto();
  var calSources = []
  var cnt = 0;
  data.forEach(function(key) {
   if(key[0]!='.')
   {
    var calObj = new Object;
    calObj.url       = 'calendars/'+key;
    var keyparts = key.split('.');
    calObj.format    = keyparts[1];
    calObj.className = 'cal_'+cnt;
    calSources.push(calObj)
    cnt += 1;
    }
  });
  
  
  var initview = document.getElementById('currview').value
  var calHeight = resizeCal();
  var calendarEl = document.getElementById('calendar')
  var  calendar = new Calendar(calendarEl, {
    initialView: initview,
    plugins: [dayGridPlugin,iCalendarPlugin, timeGridPlugin, listPlugin],
    height: calHeight,
    
    slotDuration:'01:00:00',
    eventDisplay: 'block',
    eventOrder: function(a, b) {
      var aDur = a.end && a.start ? a.end - a.start : 0;
      var bDur = b.end && b.start ? b.end - b.start : 0;
      return bDur - aDur; // longer events first
    },
    headerToolbar: {
    left: '',
    right: '',
    center: 'title',
    },
   eventSources: calSources,
   eventSourceFailure(error) {
      if (error instanceof JsonRequestError) {
        console.log(`Request to ${error.response.url} failed`)
      }
    }
  })
  
  calendarInstance = calendar;

  // Pick a random locale from the configured languages with equal probability
  var langs = (Array.isArray(languages) && languages.length) ? languages : ['en'];
  var locale = langs[Math.floor(Math.random() * langs.length)];
  if (locale !== 'en') calendar.setOption('locale', locale);

  calendar.render()
  fetchWeatherData();
  window.dispatchEvent(new Event('resize'));
}


// Reads the cached weather report and renders current conditions, 7-day forecast cells, and hourly strip.
function loadWeather()
{
  console.log('loadWeather')
  $.ajax({ // ajax call starts
    type: "POST",
    url: 'loadWeather.php',
    dataType: 'json',
    success: function(data)
    {
      if (!data || !data.current_weather) {
        console.log('Weather data unavailable');
        return;
      }
      var currentweather = data.current_weather
      if (data.showcurrentweather === false) {
        $('#cw_wind_contain, #cw_icon_big, #cw_temp_contain').hide().html('');
      } else {
        if (data.showwindspeed === false) {
          $('#cw_wind_contain').hide().html('');
        } else {
          $('#cw_wind_contain').show().html(currentweather.windspeed + ' ' + data.daily_units.windspeed_10m_max + '&nbsp;&nbsp;<i id="cw_wind_icon" class="wi wi-strong-wind"></i>');
        }
        if (data.showweathericon === false) {
          $('#cw_icon_big').hide().html('');
        } else {
          $('#cw_icon_big').show().html(currentweather.icon);
        }
        if (data.showtemperature === false) {
          $('#cw_temp_contain').hide().html('');
        } else if (data.showfeelslike_combo && currentweather.apparent_temperature != null) {
          $('#cw_temp_contain').show().html(currentweather.temperature + '<i class="wi wi-celsius"></i><span class="cw_feelslike_combo">&thinsp;/&thinsp;' + currentweather.apparent_temperature + '<i class="wi wi-celsius"></i></span>');
        } else {
          $('#cw_temp_contain').show().html(currentweather.temperature + '<i class="wi wi-celsius"></i>&nbsp;<i id="cw_temp_icon" class="wi wi-thermometer"></i>');
        }
        if (data.showfeelslike_box && currentweather.apparent_temperature != null) {
          $('#cw_feelslike_contain').show().html('Feels like ' + currentweather.apparent_temperature + '<i class="wi wi-celsius"></i>');
        } else {
          $('#cw_feelslike_contain').hide().html('');
        }
      }
      if (data.showclock) {
        $('#clock').show();
        startClock();
      } else {
        $('#clock').hide();
      }
  
      $('#lastupdated').html('<div id="time_of_weather">Last Updated: '+currentweather.time+'</div>')
    
      // Store flags so loadSunData() can use them
      sunMoonFlags = {
        showsunrisesunset: data.showsunrisesunset,
        showmoonphase:     data.showmoonphase,
      };

      $(".weatherInABox").remove();
      var daily = data.daily;
      for (let i = 0; i <7; i++)
      {

        $("td[data-date='"+daily.time[i]+"']>.fc-daygrid-day-frame>.fc-daygrid-day-top>.fc-daygrid-day-number").before('<div class="weatherInABox"><div class="dw_icon_small">'+daily.icon[i]+'</div><div class="dw_temp_contain"><div class="dw_min_temp">'+daily.temperature_2m_min[i]+'<i class="wi wi-celsius""></i></div><div class="dw_max_temp">'+daily.temperature_2m_max[i]+'<i class="wi wi-celsius""></i></div></div></div>')

        var precipArr1  = [daily.rain_sum[i],daily.showers_sum[i],daily.snowfall_sum[i]]
        var activePrecip1 = Math.max.apply(Math, precipArr1);
        var ind1 = precipArr1.indexOf(activePrecip1)

        if(ind1==0)
        {
          var activeUnits = data.daily_units.rain_sum
          var activeIcon = '<i class="wi wi-raindrop""></i>'
        }
        if(ind1==1)
        {
          var activeUnits = data.daily_units.showers_sum
          var activeIcon = '<i class="wi wi-raindrop""></i>'
        }
        if(ind1==2)
        {
          var activeUnits = data.daily_units.snowfall_sum
          var activeIcon = '<i class="wi wi-snowflake-cold""></i>'
        }

        var extraRow = '';
        if (data.showprecipqty !== false) {
          extraRow += '<span class="dw_precip_qty">'+activeIcon+' '+activePrecip1+' '+activeUnits+'</span>';
        }
        if (data.showprecipprob && daily.precipitation_probability_max) {
          extraRow += '<span class="dw_precipprob"><i class="wi wi-umbrella"></i> ' + daily.precipitation_probability_max[i] + '%</span>';
        }
        if (data.showpreciphours && daily.precipitation_hours) {
          extraRow += '<span class="dw_preciphours"><i class="wi wi-raindrop"></i> ' + daily.precipitation_hours[i] + 'h</span>';
        }
        if (data.showuvindex && daily.uv_index_max) {
          extraRow += '<span class="dw_uvindex">UV ' + Math.round(daily.uv_index_max[i]) + '</span>';
        }

        // Remove and rebuild lower box — sun/moon row is populated by loadSunData()
        $("td[data-date='"+daily.time[i]+"']>.fc-daygrid-day-frame>.dw_lower_box").remove();
        if (extraRow) {
          $("td[data-date='"+daily.time[i]+"']>.fc-daygrid-day-frame").append('<div class="dw_lower_box"><div class="dw_extra_row">'+extraRow+'</div></div>');
        }

      }

      loadSunData();

      // Build the 12-hour strip starting from the current hour.
      var hourly = data.hourly;
      $("#hourlyWeather").html('');
      var hourstring = '';
      const d = new Date();
      var start = d.getHours();
      var start1 = hourly.time.indexOf(currentweather.time)
      
      for (let j = start; j <= start+11; j++)
      {
        var precipArr  = [hourly.rain[j],hourly.showers[j],hourly.snowfall[j]]
        var activePrecip = Math.max.apply(Math, precipArr);
        var ind = precipArr.indexOf(activePrecip)

        
        if(ind==0)
        {
          var activeType = data.hourly_units.rain
          var activeIcon = '<i class="wi wi-raindrop""></i>'
        }
        if(ind==1)
        {
          var activeType = data.hourly_units.showers
          var activeIcon = '<i class="wi wi-raindrop""></i>'
        }
        if(ind==2)
        {
          var activeType = data.hourly_units.snowfall
          var activeIcon = '<i class="wi wi-snowflake-cold""></i>'
        }
        
        hourstring += '<div class="hour_box" id="hourly_box_'+j+'"><div class="hour_time">'+formatAMPM( new Date(hourly.time[j]))+'</div><div class="hour_icon">'+hourly.icon[j]+'</div><div class="hour_temp">'+hourly.temperature_2m[j]+' '+data.hourly_units.temperature_2m+'</div><div class="hour_feelslike">Feels Like</div><div class="hour_aparTemp">'+hourly.apparent_temperature[j]+' '+data.hourly_units.apparent_temperature+'</div><div class="hour_precip">'+activeIcon+' '+activePrecip +' '+activeType+'</div></div>'
      }
      if (data.showhourlyweather === false) {
        $('#BottomBox').hide();
        $("#hourlyWeather").html('');
      } else {
        $('#BottomBox').show();
        $("#hourlyWeather").show().html(hourstring);
      }
      resizeCal();
      window.dispatchEvent(new Event('resize'));
      fitOverlayStack();
    }
  });
}

// Constrains the quote/word overlay width so it doesn't slide under the weather stack.
function fitOverlayStack()
{
  var $w = $('#weatherStack');
  if ($w.is(':visible') && $w.offset() && $w.offset().left > 0) {
    $('#overlayStack').css('max-width', $w.offset().left + 'px');
  } else {
    $('#overlayStack').css('max-width', '100%');
  }
}

function startClock()
{
  function tick() {
    var now = new Date();
    var h   = String(now.getHours()).padStart(2, '0');
    var m   = String(now.getMinutes()).padStart(2, '0');
    $('#clock').text(h + ':' + m);
  }
  tick();
  if (clockTimer) clearInterval(clockTimer);
  clockTimer = setInterval(tick, 10000);
}

// Fetches sunrise/sunset/moon-phase for every day in the current month and
// populates the lower box of every calendar cell (not just the 7 forecast days).
function loadSunData() {
  if (sunMoonFlags.showsunrisesunset === false && sunMoonFlags.showmoonphase === false) return;
  var now = new Date();
  $.ajax({
    url: 'loadSunData.php',
    data: { year: now.getFullYear(), month: now.getMonth() + 1 },
    dataType: 'json',
    success: function(data) {
      if (!data) return;
      $.each(data, function(date_str, dayData) {
        var $frame = $("td[data-date='" + date_str + "']>.fc-daygrid-day-frame");
        if (!$frame.length) return;

        // Remove any existing sun row (from a previous render/refresh)
        $frame.find('.dw_sun_row').remove();

        if (sunMoonFlags.showsunrisesunset === false) return;

        var moonIcon = (sunMoonFlags.showmoonphase && dayData.moon_phase)
          ? '<span class="dw_moon_phase">' + dayData.moon_phase + '</span>'
          : '';
        var sunRow = '<div class="dw_sun_row">'
          + '<span class="dw_sunrise_contain"><i class="wi wi-sunrise"></i> ' + dayData.sunrise + '</span>'
          + '<span class="dw_sunset_contain"><i class="wi wi-sunset"></i> '   + dayData.sunset  + '</span>'
          + moonIcon
          + '</div>';

        var $lowerBox = $frame.find('.dw_lower_box');
        if ($lowerBox.length) {
          $lowerBox.append(sunRow);
        } else {
          $frame.append('<div class="dw_lower_box">' + sunRow + '</div>');
        }
      });
    }
  });
}

//Converts the time to AM/PM
function formatAMPM(date) {
  var hours = date.getHours();
  var minutes = date.getMinutes();
  var ampm = hours >= 12 ? 'pm' : 'am';
  hours = hours % 12;
  hours = hours ? hours : 12; // the hour '0' should be '12'
  minutes = minutes < 10 ? '0'+minutes : minutes;
  var strTime = hours + ':' + minutes + ' ' + ampm;
  return strTime;
}


// Triggers background pre-processing of any new images (rename + blur/colour crop via ImageMagick).
function procImage()
{
  $.ajax({
    type: "POST",
    url: 'processImages.php',
    dataType: 'json',
  });
}


// Calls the server to fetch fresh weather from Open-Meteo and cache it, then renders the result.
function fetchWeatherData()
{
  console.log('Fetch Weather')
  $.ajax({
    type: "POST",
    url: 'fetchWeather.php',
    dataType: 'html',
    success: function() { loadWeather(); }
  });
}

// Picks a random background image and injects the derived colour scheme into :root.
function SelectImages()
{
  console.log('SelectImages')
  $.ajax({
    type: "POST",
    url: 'SelectImages.php',
    dataType: 'json',
    success: function(data1)
    {
      originalPhotoSrc = data1.image;
      $('#photo').attr('src', data1.image)
      $('#blankBottomBox').html('<style>html,body { background-image:'+data1.blurry+'; background-size: cover; background-repeat: no-repeat; }'+data1.alpha_color+'</style>')
      
    }
  });
}

// Downloads fresh .ics files from the configured calendar URLs into dist/calendars/.
function fetchCalData()
{
  console.log('Fetch Calendars')
  $.ajax({
    type: "POST",
    url: 'fetchCalendar.php',
    dataType: 'html',
    success: function() { console.log('Calendars Fetched'); }
  });
}



// Calculates the correct calendar height to fill the space below the photo and above the hourly strip.
function resizeCal()
{
  var cal_overlap = 178;
  var bottomBoxH = $('#BottomBox').is(':visible') ? $('#BottomBox').height() : 0;
  var h = ($('#screenContain').height() - $('#photoContain').height() - bottomBoxH) + cal_overlap;
  $('#calendar').height(h);
  if (calendarInstance) calendarInstance.setOption('height', h);
  return h;
}

// Cycles to the next valid view (month → week → recipe → notes → ...).
function toggleMode(evt)
{
  console.log('Toggle Calendar Mode', evt.keyCode)
  const oldcal = document.getElementById('calendar')
  const cal = document.createElement("div");
  cal.id = 'calendar'
  cal.height =  oldcal.height
  oldcal.replaceWith(cal)
  var currview = document.getElementById('currview')
  var validviews = document.getElementById('validviews')
  var viewarr = validviews.value.split(",")
  var currindex = viewarr.indexOf(currview.value)

  if(currindex == viewarr.length -1)
  {
    currview.value = viewarr[0];
  }else
  {
    currview.value = viewarr[currindex+1]
  }

  reloadCal_No_Image_Change()
}

function toggleCal(evt)
{
}

// Full refresh: picks a new background image and rebuilds the calendar.
function reloadCal()
{
  console.log('Refresh Calendar')
  calendarInstance = null;
  const oldcal = document.getElementById('calendar')
  const cal = document.createElement("div");
  cal.id = 'calendar'
  cal.height =  oldcal.height
  oldcal.replaceWith(cal)
  SelectImages()
}
// Hard page reload — used by the daily scheduler to pick up date changes cleanly.
function hardReloadCal()
{
  location.reload();
}

// Rebuilds the calendar without changing the background image (used when toggling views).
function reloadCal_No_Image_Change()
{
  console.log('Refresh Calendar (No image)')
  calendarInstance = null;
  const oldcal = document.getElementById('calendar')
  const cal = document.createElement("div");
  cal.id = 'calendar'
  cal.height =  oldcal.height
  oldcal.replaceWith(cal)
  loadCal();
}

// Fetches ski hill conditions and shows/hides the ski overlay accordingly.
function loadSkiData()
{
  console.log('Load Ski Data')
  $.ajax({
    type: "POST",
    url: 'loadSki.php',
    dataType: 'html',
    success: function(data)
    {
      if (data.trim()) {
        $('#ski').html(data).show();
      } else {
        $('#ski').hide();
      }
      repositionSki();
    }
  });
}


// Polls toggles.json (updated by GPIO hardware script) and shows/hides each calendar layer.
function showHideCalendars()
{
  $.ajax({
    type: "POST",
    url: 'toggles.json',
    dataType: 'json',
    success: function(data)
    {
      var i = 0;
      $.each(data, function(key, value) {
        if(value == 1)
        {
          $('.cal_'+i).show()
        }else
        {
          $('.cal_'+i).hide()
        }
        i++;
      });
    }
  });
}