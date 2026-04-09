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

//Listen for Key Presses, R for Refresh, T for Toggle new mode and the number keys will show/hide certain calendars.
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
    //These are the num keys possibly will not work as the will need to activate on keydown and keyup
    if ( evt.keyCode >=48 && evt.keyCode <58) {
        toggleCal(evt);
    }
});


//When the Photo has been loaded, Then the calendar will be loaded, (timing needed to resize to correct)
$("#photo").bind('load', function() {
  console.log('Load Calendar')
  loadCal()
});


//This will load the Calendar but first we need to ensure that we know what files we can load
function loadCal()
{
  var initview = document.getElementById('currview').value
  $.ajax({ 
   type: "POST",
   url: 'loadCalsAndNotes.php',
   dataType: 'json', 
   success: function(data)
   {
      //If we successfully get the Calendar and Notes Filenames then we can build the calendars and the notes pages depending on the value pf the hidden text box on the page '#currview'
      if (data.validviews) {
        document.getElementById('validviews').value = data.validviews;
        // If current view is no longer valid, reset to first view
        var viewarr = data.validviews.split(',');
        if (!viewarr.includes(initview)) {
          initview = viewarr[0];
          document.getElementById('currview').value = initview;
        }
      }
       var calHeight = resizeCal();
       console.log(initview)
       if(initview=='timeGridWeek' || initview =='dayGridMonth')
       {
         makeCalendar(data.calendars);
       }else 
       {
         if(initview=='notes')
         {
            makeNotes();
         }else
         {
           //Recipe
           makeRecipe();
         }
       }
   }
  });
}


//INCOMPLETE - We will need to find a simple notetaking app to load at this point, ideally it will happen in the background and drop it in the notes folder then this section will just load the filed from the notes folder. 
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
    console.log(data)
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
        $('#quote').html('"' + data.q + '"<span class="quote-author">— ' + data.a + '</span>');
        repositionSki();
      }
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



//Actually Make the Calendar 
function makeCalendar(data)
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
  
  //RANDOMLY SWITCH THE CALENDAR BETWEEN FRENCH AND ENGLISH FOR EQUAL REPRESENTATION
  var random_boolean = Math.random() < 0.5;
  if(random_boolean)
  {
    calendar.setOption('locale', 'fr');
  }
  calendar.render()
  fetchWeatherData();
  window.dispatchEvent(new Event('resize'));
}


//This Reads the weather/report.json and creates the weather icons ans sets the locations of the 
function loadWeather()
{
  console.log('loadWeather')
  $.ajax({ // ajax call starts
    type: "POST",
    url: 'loadWeather.php',
    dataType: 'json', // Choosing a JSON datatype
   
    success: function(data) // Variable data contains the data we get from serverside
    {
      if (!data || !data.current_weather) {
        console.log('Weather data unavailable');
        $('#weather').html('<div id="cw_wind_contain">&nbsp;</div><div id="cw_icon_big"><i class="wi wi-cloudy"></i></div><div id="cw_temp_contain">&nbsp;</div>');
        return;
      }
      //Top Big Current Weather Display
      //This would be cleaner if we did this in PHP and then just filled #weather with the already created box
      var currentweather = data.current_weather
      var clockEl = data.showclock ? '<div id="clock"></div>' : '';
      $('#weather').html('<div id="cw_wind_contain">'+currentweather.windspeed+' '+data.daily_units.windspeed_10m_max+'  <i id="cw_wind_icon" class="wi wi-strong-wind"></i></div><div id="cw_icon_big">'+currentweather.icon+'</div><div id="cw_temp_contain">'+currentweather.temperature+'<i class="wi wi-celsius""></i> <i id="cw_temp_icon" class="wi wi-thermometer"></i></div>'+clockEl);
      if (data.showclock) startClock();
  
      //Set the last time the weather was updated
      $('#lastupdated').html('<div id="time_of_weather">Last Updated: '+currentweather.time+'</div>')
    
      $(".weatherInABox").remove();
      var daily = data.daily;
      //Put the weather data on each Day Box
      for (let i = 0; i <7; i++) 
      {

        $("td[data-date='"+daily.time[i]+"']>.fc-daygrid-day-frame>.fc-daygrid-day-top>.fc-daygrid-day-number").before('<div class="weatherInABox"><div class="dw_icon_small">'+daily.icon[i]+'</div><div class="dw_temp_contain"><div class="dw_min_temp">'+daily.temperature_2m_min[i]+'<i class="wi wi-celsius""></i></div><div class="dw_max_temp">'+daily.temperature_2m_max[i]+'<i class="wi wi-celsius""></i></div></div></div>')
        
        var SUNRISE = new Date(daily.sunrise[i]);
        var SUNSET = new Date(daily.sunset[i]);
        
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
        
        var precipBox = '<div class="dw_precip_contain">'+activeIcon+' '+activePrecip1+' '+activeUnits+' </div>';
        
        $("td[data-date='"+daily.time[i]+"']>.fc-daygrid-day-frame>.dw_lower_box").remove();
        
        $("td[data-date='"+daily.time[i]+"']>.fc-daygrid-day-frame").append('<div class="dw_lower_box"><div class="dw_sun_contain"><div class="dw_sunrise_contain"><i class="wi wi-sunrise""></i>'+formatAMPM(SUNRISE)+'</div><div class="dw_sunset_contain"><i class="wi wi-sunset"></i>'+formatAMPM(SUNSET)+'</div></div>'+precipBox+'</div>');
  
      }
      //This puts hourly weather on the  bottom of the page, could be nicer on weekly view to put is at correct hours... 
      var hourly = data.hourly;
      
      $("#hourlyWeather").html('')
      var hourstring = '';
      //Get Current Hour Index
 
      const d = new Date();
      var start = d.getHours();
      var start1 = hourly.time.indexOf(currentweather.time)
      
      for (let j = start; j <=start+11; j++) 
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
      $("#hourlyWeather").html(hourstring)
      fitOverlayStack();
    }
  });
}

function fitOverlayStack()
{
  var $w = $('#weather');
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


// Calls the PHP page to process the image image processing gives the image a random name and creats a corresponding file in images_supports to create the blrry background of the right colour
function procImage()
{
  console.log('ProcessImages')
  $.ajax({ // ajax call starts
    type: "POST",
    url: 'processImages.php',
    dataType: 'json', // Choosing a JSON datatype
    success: function(data) // Variable data contains the data we get from serverside
    {
      console.log('imagesProcessed')
    }
  });
}


//Get the latest weather from the open meteo API and save the information in the weather/report.json file 
function fetchWeatherData()
{
  console.log('Fetch Weather')
  $.ajax({ // ajax call starts
    type: "POST",
    url: 'fetchWeather.php',
    dataType: 'html', // Choosing a JSON datatype
    success: function(data) // Variable data contains the data we get from serverside
    {
      console.log(data)
      loadWeather()
    }
  });
}

//Select a random image from the folder to update if needed. 
function SelectImages()
{
  console.log('SelectImages')
  $.ajax({ // ajax call starts
    type: "POST",
    url: 'SelectImages.php',
    dataType: 'json', // Choosing a JSON datatype
    success: function(data1) // Variable data contains the data we get from serverside
    {
      console.log(data1)
      originalPhotoSrc = data1.image;
      $('#photo').attr('src', data1.image)
      $('#blankBottomBox').html('<style>html,body { background-image:'+data1.blurry+'; background-size: cover; background-repeat: no-repeat; }'+data1.alpha_color+'</style>')
      
    }
  });
}

function fetchCalData()
{
  console.log('Fetch Calendars')
  $.ajax({ // ajax call starts
    type: "POST",
    url: 'fetchCalendar.php',
    dataType: 'html', // Choosing a JSON datatype
    success: function(data) // Variable data contains the data we get from serverside
    {
      console.log('Calendars Fetched')
      //reloadCal_No_Image_Change()
    }
  });
}



function resizeCal()
{
  var cal_overlap = 178;
  console.log($('#screenContain').height())
  console.log($('#photoContain').height())
  console.log($('#BottomBox').height())
  console.log(($('#screenContain').height()-$('#photoContain').height()-$('#BottomBox').height())+cal_overlap)
  return $('#calendar').height(($('#screenContain').height()-$('#photoContain').height()-$('#BottomBox').height())+cal_overlap);
}

function toggleMode(evt)
{
  console.log('Toggle Calendar Mode')
  console.log(evt.keyCode )
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
  console.log('Toggle Calendar On/Off')
  console.log(evt.keyCode )
}

function reloadCal()
{
  console.log('Refresh Calendar')
  const oldcal = document.getElementById('calendar')
  const cal = document.createElement("div");
  cal.id = 'calendar'
  cal.height =  oldcal.height
  oldcal.replaceWith(cal)
  SelectImages()  
}
function hardReloadCal()
{
  location.reload();
}

function reloadCal_No_Image_Change()
{
  console.log('Refresh Calendar (No image)')
  const oldcal = document.getElementById('calendar')
  const cal = document.createElement("div");
  cal.id = 'calendar'
  cal.height =  oldcal.height
  oldcal.replaceWith(cal)
  loadCal();
}

function loadSkiData()
{
  console.log('Load Ski Data')
  $.ajax({ // ajax call starts
    type: "POST",
    url: 'loadSki.php',
    dataType: 'html', // Choosing a JSON datatype
    success: function(data) // Variable data contains the data we get from serverside
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


function showHideCalendars()
{
  console.log('switch check')
  $.ajax({ // ajax call starts
    type: "POST",
    url: 'toggles.json',
    dataType: 'json', // Choosing a JSON datatype
    success: function(data) // Variable data contains the data we get from serverside
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