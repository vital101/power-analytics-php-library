# WP Power Analytics Lib

This is the WP Power Analytics PHP library for integrating with WordPress and other PHP projects.

## Usage

Initialize the library as early as possible in your plugin code.

    require plugin_dir_path( __FILE__ ) . "includes/power_analytics.php";
    $powerAnalyticsUUID = "Your Power Analytics Product UUID";
    $PowerAnalytics = new WordPressPowerAnalytics(
        $powerAnalyticsUUID,
        __FILE__,
        "your-plugin-sluhg"
    );
    $PowerAnalytics->initialize();

## Event Tracking

    global $PowerAnalytics;
	$PowerAnalytics->track($eventName, $eventValue);
