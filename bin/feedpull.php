<? include get_cfg_var("cartulary_conf") . '/includes/env.php'; ?>
<? include "$confroot/$templates/php_bin_init.php" ?>
<?
//Let's not run twice
if (($pid = cronHelper::lock()) !== FALSE) {

    //Track the river scan time
    $tstart = time();

    //Do we want to scan error feeds or normal feeds?
    $action = "";
    if (in_array("error", $argv)) {
        $action = "error";
        //add_admin_log_item("Scanned error feeds.", "Aggregator");
        echo "Scanning error feeds.\n\n";
        loggit(3, "Doing an error feed scan.");
    }
    if (in_array("old", $argv)) {
        $action = "old";
        //add_admin_log_item("Scanned old feeds.", "Aggregator");
        echo "Scanning old feeds.\n\n";
        loggit(3, "Doing an old feed scan.");
    }

    //Get the proper feed list
    if ($action == "error") {
        $feeds = get_error_feeds();
    } else
    if ($action == "old") {
        $feeds = get_old_feeds();
    } else {
        $feeds = get_all_feeds();
    }

    $totalfeeds = count($feeds);
    $totaltime = $totalfeeds * 3;

    //Only scan the oldest 33% of feeds on non-error/old passes
    if ($action == "error" || $action == "old") {
        $scancount = $totalfeeds;
    } else {
        $scancount = round(($totalfeeds * .33) + 1);
    }

    loggit(3, " ----- Start scan of [$scancount] of [$totalfeeds] feeds.");
    echo "Scanning [$scancount] of [$totalfeeds] feeds.\n\n";

    // Walk the feed list and pull fresh content for each
    $ccount = 0;
    $ncount = 0;
    $dcount = 0;
    foreach ($feeds as $feed) {
        loggit(1, "Checking feed: [" . $feed['title'] . " | " . $feed['url'] . "]...");
        echo "Checking feed: [" . $feed['title'] . " | " . $feed['url'] . "]...\n";

        //Make a timestamp
        $fstart = time();

        $subcount = get_feed_subscriber_count($feed['id']);
        loggit(1, "Feed: [" . $feed['title'] . "] has: [$subcount] subscribers.");
        echo "    Feed: [" . $feed['title'] . "] has: [$subcount] subscribers.\n";

        //Pull the content
        if ($subcount > 0) {
            loggit(1, "Checking feed: [ $ccount | " . $feed['title'] . " | " . $feed['url'] . "].");
            $result = fetch_feed_content($feed['id']);
            if (empty($result)) {
                loggit(1, "Error getting items for feed: [" . $feed['title'] . " | " . $feed['url'] . "]");
                echo "    Error getting items for feed: [" . $feed['title'] . " | " . $feed['url'] . "]\n";
            } else {
                loggit(3, "Feed: [" . $feed['title'] . " | " . $feed['url'] . "] updated.");
                echo "    Feed updated.\n";
            }

            $ccount++;
        } else {
            loggit(3, "No subscribers for: [" . $feed['title'] . " | " . $feed['id'] . "]. Deleting feed from the system.");
            echo "    No subscribers. Removing feed, items and catalog links.\n";
            delete_feed($feed['id']);
            $dcount++;
        }

        echo "      It took " . (time() - $fstart) . " seconds to scan this feed.\n";
        loggit(1, "It took [" . (time() - $fstart) . "] seconds to scan this feed.");
        echo "\n";

        //We stop scanning if this scan has taken longer than expected
        if ((time() - $tstart) > $totaltime) {
            loggit(3, "Stop scan because it took longer than the expected: [$totaltime] seconds.");
            break;
        }

        //We stop scanning once we hit our feed count limit for this pass
        if ($ccount >= $scancount) {
            break;
        }
    }
    echo "\n";

    //Calculate time took to scan the river
    loggit(3, "It took " . (time() - $tstart) . " seconds to scan [$ccount] of [$totalfeeds] feeds.");
    loggit(3, "Total checked: [$ccount]. Updated: [$ncount]. Deleted: [$dcount].");
    echo "      It took " . (time() - $tstart) . " seconds to scan [$ccount] of [$totalfeeds] feeds.\n";
    echo "Total checked: [$ccount]. Updated: [$ncount]. Deleted: [$dcount].\n";


    // Log and leave
    loggit(3, "Feedscan finished.");

    //Remove the lock file
    cronHelper::unlock();
}
exit(0);