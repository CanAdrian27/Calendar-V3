<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$scheduleFile = 'schedule.json';
$defaults = [
    'image_hour'           => 4,
    'image_minute'         => 0,
    'word_hour'            => 4,
    'word_minute'          => 30,
    'quote_hour'           => 4,
    'quote_minute'         => 30,
    'weather_interval_min' => 1,
    'calendar_interval_min'=> 1,
    'ski_interval_min'     => 30,
];

$json = @file_get_contents($scheduleFile);
$cfg  = $json ? array_merge($defaults, json_decode($json, true) ?? []) : $defaults;

$saved = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    function parseTime($key) {
        $val = trim($_POST[$key] ?? '');
        if (!preg_match('/^(\d{1,2}):(\d{2})$/', $val, $m)) return null;
        return [(int)$m[1], (int)$m[2]];
    }
    function parseInt($key, $min = 1, $max = 1440) {
        $v = (int)($_POST[$key] ?? 1);
        return max($min, min($max, $v));
    }

    $imgTime   = parseTime('image_time');
    $wordTime  = parseTime('word_time');
    $quoteTime = parseTime('quote_time');

    if (!$imgTime || !$wordTime || !$quoteTime) {
        $error = 'Invalid time value — use HH:MM format.';
    } else {
        $new = [
            'image_hour'            => $imgTime[0],
            'image_minute'          => $imgTime[1],
            'word_hour'             => $wordTime[0],
            'word_minute'           => $wordTime[1],
            'quote_hour'            => $quoteTime[0],
            'quote_minute'          => $quoteTime[1],
            'weather_interval_min'  => parseInt('weather_interval_min'),
            'calendar_interval_min' => parseInt('calendar_interval_min'),
            'ski_interval_min'      => parseInt('ski_interval_min'),
        ];
        if (file_put_contents($scheduleFile, json_encode($new, JSON_PRETTY_PRINT)) !== false) {
            $cfg   = $new;
            $saved = true;
        } else {
            $error = 'Could not write schedule.json — check file permissions.';
        }
    }
}

function fmtTime($h, $m) {
    return str_pad($h, 2, '0', STR_PAD_LEFT) . ':' . str_pad($m, 2, '0', STR_PAD_LEFT);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin — Schedule</title>
    <?php include('adminSharedStyles.php'); ?>
    <style>
        .schedule-row {
            display: grid;
            grid-template-columns: 1fr 200px;
            align-items: center;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid #f0f2f5;
        }
        .schedule-row:last-child { border-bottom: none; }
        .schedule-label { font-size: 14px; font-weight: 500; }
        .schedule-label small { display: block; color: #888; font-weight: 400; font-size: 12px; margin-top: 2px; }
        input[type=time], input[type=number] {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #d0d5dd;
            border-radius: 6px;
            font-size: 14px;
        }
        input[type=time]:focus, input[type=number]:focus {
            outline: none;
            border-color: #4f6ef7;
            box-shadow: 0 0 0 3px rgba(79,110,247,.15);
        }
        .input-suffix { display: flex; align-items: center; gap: 8px; }
        .input-suffix span { font-size: 13px; color: #666; white-space: nowrap; }
        .section-title {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: #4f6ef7;
            margin: 18px 0 6px;
        }
        .fixed-badge {
            display: inline-block;
            background: #f0f2f5;
            border: 1px solid #d0d5dd;
            color: #888;
            border-radius: 6px;
            padding: 6px 12px;
            font-size: 13px;
        }
    </style>
</head>
<body>
<?php include('adminNav.php'); ?>

<h1>Schedule</h1>

<?php if ($saved): ?>
    <div class="notice success">✓ Schedule saved. Changes take effect on next page load.</div>
<?php elseif ($error): ?>
    <div class="notice error">⚠ <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST">
    <div class="card">
        <h2>Daily — fires once per day at the configured time</h2>

        <div class="schedule-row">
            <div class="schedule-label">
                Background image refresh
                <small>Full page reload with a new background photo</small>
            </div>
            <input type="time" name="image_time"
                   value="<?= fmtTime($cfg['image_hour'], $cfg['image_minute']) ?>">
        </div>

        <div class="schedule-row">
            <div class="schedule-label">
                Word of the day
                <small>Fetches the new word and translations</small>
            </div>
            <input type="time" name="word_time"
                   value="<?= fmtTime($cfg['word_hour'], $cfg['word_minute']) ?>">
        </div>

        <div class="schedule-row">
            <div class="schedule-label">
                Quote of the day
                <small>Fetches the new daily inspirational quote</small>
            </div>
            <input type="time" name="quote_time"
                   value="<?= fmtTime($cfg['quote_hour'], $cfg['quote_minute']) ?>">
        </div>
    </div>

    <div class="card">
        <h2>Intervals — repeat on a fixed cycle</h2>

        <div class="schedule-row">
            <div class="schedule-label">
                Weather
                <small>Fetches fresh weather data from Open-Meteo</small>
            </div>
            <div class="input-suffix">
                <input type="number" name="weather_interval_min"
                       value="<?= (int)$cfg['weather_interval_min'] ?>" min="1" max="1440">
                <span>min</span>
            </div>
        </div>

        <div class="schedule-row">
            <div class="schedule-label">
                Calendar sync
                <small>Downloads updated .ics files from calendar URLs</small>
            </div>
            <div class="input-suffix">
                <input type="number" name="calendar_interval_min"
                       value="<?= (int)$cfg['calendar_interval_min'] ?>" min="1" max="1440">
                <span>min</span>
            </div>
        </div>

        <div class="schedule-row">
            <div class="schedule-label">
                Ski conditions
                <small>Reloads ski hill snow report</small>
            </div>
            <div class="input-suffix">
                <input type="number" name="ski_interval_min"
                       value="<?= (int)$cfg['ski_interval_min'] ?>" min="1" max="1440">
                <span>min</span>
            </div>
        </div>
    </div>

    <div class="card">
        <h2>Fixed — not configurable</h2>
        <div class="schedule-row">
            <div class="schedule-label">
                GPIO toggle check
                <small>Polls hardware switches to show/hide calendars</small>
            </div>
            <span class="fixed-badge">Every 1 second</span>
        </div>
    </div>

    <button type="submit" class="btn-save">Save Schedule</button>
</form>

</body>
</html>
