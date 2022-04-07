<?php
/**
 * An overview on the transactions with the server.
 *
 * @author mgSoft
 */

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";
include_once "../classes/client_tx_statistics.php";
$client_id = (isset($_GET["clientID"])) ? intval($_GET["clientID"]) : 0; // identify client for statistics to
                                                                // show
if (! $client_id) {
    $toolbox->display_error("Nicht zulässig.", 
            "Die Seite '" . $user_requested_file . "' muss mit der Angabe der efaCloudUserID des zu berichtenden " .
            "Clients aufgerufen werden.",
            $user_requested_file);
}

$statistics = new Client_tx_statistics($toolbox, $client_id);

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');

?>

<div class="w3-container">
	<h3>Internetzugriffs-Statistik, übermittelt von Client #<?php echo $client_id ?></h3>
	<p>Letzte Aktualisierung durch den Client <?php
echo date("Y-m-d H:i:s", $statistics->last_record);
?><br>Erfasste Internet-Pakete: <?php
echo $statistics->count_iam_records;
?>, Transaktionen: <?php
echo $statistics->count_txq_records;
?>.
	</p>
</div>
<!-- Hidden modal for large charts -->
<div id="chartModal" class="modal">
	<!-- Modal content -->
	<div class="modal-content">
		<span class="closeModal">&times;</span>
		<h5 id='modal-header'></h5>
		<p id="LARGE_CHART"></p>
	</div>
</div>

<!-- Projects grid (2 columns, 2 rows; images must have the same size)-->
<div class="w3-auto">
	<div class="w3-col l2">
		<div class="w3-container w3-grayscale">
			<span id="container_duration" class="show_hover"></span>
			<p></p>
		</div>
	</div>
	<div class="w3-col l2">
		<div class="w3-container w3-grayscale">
			<span id="container_count" class="show_hover"></span>
			<p></p>
		</div>
	</div>
	<div class="w3-auto"></div>
	<div class="w3-col l2">
		<div class="w3-container w3-grayscale">
			<span id="transactions_wait_time" class="show_hover"></span>
			<p></p>
		</div>
	</div>
	<div class="w3-col l2">
		<div class="w3-container w3-grayscale">
			<span id="transactions_count" class="show_hover"></span>
			<p></p>
		</div>
	</div>
</div>


<!-- Google Charts usage to display graphs. -->
<!-- Load the AJAX API -->
<script type="text/javascript"
	src="https://www.gstatic.com/charts/loader.js"></script>
<script type="text/javascript" src="../js/jQuery_3.3.1.js"></script>

<script type="text/javascript">
    var data_container_duration = <?php echo $statistics->get_container_duration_chart_data(); ?>;
    var data_container_count = <?php echo $statistics->get_container_count_chart_data(); ?>;
    var data_transactions_wait_time = <?php echo $statistics->get_transactions_wait_time_chart_data(); ?>;
    var data_transactions_count = <?php echo $statistics->get_transactions_count_chart_data(); ?>;

    var options_container_duration = {
            width: 400,
            height: 400,
            backgroundColor: { fill:'transparent' },
            colors: [ '#0c009f', '#888'  ],
            legend: { position: 'top', maxLines: 2 },
            bar: { groupWidth: '50%' },
            title : 'Mittlere Internetzugriffszeit',
            vAxis: {title: 'ms', scaleType: 'log'},
            hAxis: {title: 'Tag'},
            seriesType: 'line',
            isStacked: false
        };
    var options_container_count = {
            width: 400,
            height: 400,
            backgroundColor: { fill:'transparent' },
            colors: [ '#0c009f', '#888'  ],
            legend: { position: 'top', maxLines: 2 },
            bar: { groupWidth: '50%' },
            title : 'Anzahl Internetzugriffe',
            vAxis: {title: '#'},
            hAxis: {title: 'Tag'},
            seriesType: 'bars',
            isStacked: true
        };
    /*
    efa colors: accent red #a3001d, green #00982d, blue #0c009f
    efa colors light: accent red #c17b87, green #91c09f, blue #8079dd
    efa colors gray: dark #333, medium #888, light #ccc
   */
    var options_transactions_wait_time = {
            width: 350,
            height: 350,
            backgroundColor: { fill:'transparent' },
            colors: [ '#0c009f', '#00982d', '#91c09f','#a3001d', '#c17b87', '#8079dd' ],
            legend: { position: 'top', maxLines: 2 },
            bar: { groupWidth: '50%' },
            title : 'Mittlere Wartezeit',
            vAxis: {title: 'ms',scaleType: 'log'},
            hAxis: {title: 'Tag'},
            seriesType: 'line',
            isStacked: false
        };
    var options_transactions_count = {
            width: 350,
            height: 350,
            backgroundColor: { fill:'transparent' },
            colors: [ '#0c009f', '#00982d', '#91c09f','#a3001d', '#c17b87', '#8079dd' ],
            legend: { position: 'top', maxLines: 2 },
            bar: { groupWidth: '50%' },
            title : 'Anzahl Transaktionen',
            vAxis: {title: '#'},
            hAxis: {title: 'Tag'},
            seriesType: 'bars',
            isStacked: true
        };

	// the following cod provides the option to show a large chart in the modal.
    var modal = document.getElementById("chartModal");
    // Get the <span> element that closes the modal
    var btnClose = document.getElementsByClassName("closeModal")[0];
    // When the user clicks on <span> (x), close the modal
    btnClose.onclick = function() {
      modal.style.display = "none";
    };
    function zoom(options, factor) {
        options.width = options.width * factor;
        options.height = options.height * factor;
    };
    $('#container_duration').click(function () {
        modal.style.display = "block";
        // load data
        var data = google.visualization.arrayToDataTable(data_container_duration);
        // Instantiate and draw our chart, passing in some options.
        var chart = new google.visualization.ComboChart(document.getElementById('LARGE_CHART'));
        zoom(options_container_duration, 2);
        chart.draw(data, options_container_duration, 2);
        zoom(options_container_duration, 0.5);
    });
    $('#container_count').click(function () {
        modal.style.display = "block";
        // load data
        var data = google.visualization.arrayToDataTable(data_container_count);
        // Instantiate and draw our chart, passing in some options.
        var chart = new google.visualization.ComboChart(document.getElementById('LARGE_CHART'));
        zoom(options_container_countm, 2);
        chart.draw(data, options_container_count, 2);
        zoom(options_container_count, 0.5);
    });
    $('#transactions_wait_time').click(function () {
        modal.style.display = "block";
        var data = google.visualization.arrayToDataTable(data_transactions_wait_time);
        var chart = new google.visualization.ComboChart(document.getElementById('LARGE_CHART'));
        zoom(options_transactions_wait_time, 2);
        chart.draw(data, options_transactions_wait_time, 2);
        zoom(options_transactions_wait_time, 0.5);
    });
    $('#transactions_count').click(function () {
        modal.style.display = "block";
        var data = google.visualization.arrayToDataTable(data_transactions_count);
        var chart = new google.visualization.ComboChart(document.getElementById('LARGE_CHART'));
        zoom(options_transactions_count, 2);
        chart.draw(data, options_transactions_count, 2);
        zoom(options_transactions_count, 0.5);
    });

    // basic chart display
    function drawCharts() {
        var data = google.visualization.arrayToDataTable(data_container_duration);
        var chart = new google.visualization.ComboChart(document.getElementById('container_duration'));
        chart.draw(data, options_container_duration);
        var data = google.visualization.arrayToDataTable(data_container_count);
        var chart = new google.visualization.ComboChart(document.getElementById('container_count'));
        chart.draw(data, options_container_count);
        var data = google.visualization.arrayToDataTable(data_transactions_wait_time);
        var chart = new google.visualization.ComboChart(document.getElementById('transactions_wait_time'));
        chart.draw(data, options_transactions_wait_time);
        var data = google.visualization.arrayToDataTable(data_transactions_count);
        var chart = new google.visualization.ComboChart(document.getElementById('transactions_count'));
        chart.draw(data, options_transactions_count);
    }

    $( document ).ready(function() {
        // Load the Visualization API and the corechart package.
        google.charts.load('current', {'packages':['corechart'], 'language': 'de'});
        // Set a callback to run when the Google Visualization API is loaded.
        google.charts.setOnLoadCallback(drawCharts);
        // Callback that creates and populates a data table, instantiates the charts, 
        // passes in the data and draws all charts.
    });
</script>

<?php
end_script();