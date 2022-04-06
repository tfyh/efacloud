<?php

/**
 * class file for client server interface statistics.
 */
class Client_tx_statistics
{

    /**
     * Table of all internet access statistics records
     */
    private $iam_statistics;

    /**
     * Table of all transaction statistics records
     */
    private $txc_statistics;

    /**
     * Table of all transaction statistics records
     */
    private $count_of_days;

    /**
     * Array with the last days as String.
     */
    private $last_dates;

    /**
     * Array with the last days start time (00:00:00) as timestamp.
     */
    private $last_dates_from;

    /**
     * Array to hold the data for the graph "container duration".
     */
    private $container_duration_chart_data;

    /**
     * Array to hold the data for the graph "container count".
     */
    private $container_count_chart_data;

    /**
     * Array to hold the data for the graph "transactions wait time".
     */
    private $transactions_wait_time_chart_data;

    /**
     * Array to hold the data for the graph "transactions count".
     */
    private $transactions_count_chart_data;

    /**
     * time of last client data upload. Used to display last upload time to user.
     */
    public $last_record;

    /**
     * Count of internet container records.
     */
    public $count_iam_records;

    /**
     * Count of transaction records.
     */
    public $count_txq_records;

    /**
     * Initialize all field for the statistics to be gathered.
     *
     * @param Tfyh_toolbox $toolbox
     *            The toolbox for the csv handling functions.
     * @param int $client_id
     *            The efaCloudUserID of the client, for which the statistics are gathered
     * @param int $count_of_days
     *            The count of days for which the statistics shall be gathered
     */
    private function read_statistics (Tfyh_toolbox $toolbox, int $client_id, int $count_of_days)
    {
        // initialize graph array headers
        $iam_columns = ["Day" => 0,"completed" => 1,"other" => 2
        ];
        $this->container_duration_chart_data = array(array('Day','completed','other'
        )
        );
        $this->container_count_chart_data = array(array('Day','completed','other'
        )
        );
        $txq_columns = [
                "Day" => 0,
                "upload" => 1,
                "insert" => 2,
                "update" => 3,
                "synch" => 4,
                "select" => 5,
                "keyfixing" => 6,
                "other" => 7
        ];
        $this->transactions_wait_time_chart_data = array(
                array('Day','upload','insert','update','synch','select','keyfixing','other'
                )
        );
        $this->transactions_count_chart_data = array(
                array('Day','upload','insert','update','synch','select','keyfixing','other'
                )
        );
        
        // initialize graph array values
        $this->count_of_days = $count_of_days;
        $now = time();
        $secday = 24 * 3600;
        $this->last_fortnight = [];
        for ($i = 0; $i < $count_of_days; $i ++) {
            $this->last_dates[$i] = date("Y-m-d", $now - $i * $secday);
            $this->last_dates_from = strtotime($this->last_dates[$i]);
            // initiate pivot table for CONTAINER duration. Each element is a record with the date,
            // and the duration sum for completed and other
            $this->container_duration_chart_data[$i + 1] = [$this->last_dates[$i],0,0
            ];
            $this->container_count_chart_data[$i + 1] = [$this->last_dates[$i],0,0
            ];
            // initiate pivot table for TRANSACTION duration. Each element is a record with the date,
            // and the duration sum per type
            $this->transactions_wait_time_chart_data[$i + 1] = [$this->last_dates[$i],0,0,0,0,0,0,0
            ];
            $this->transactions_count_chart_data[$i + 1] = [$this->last_dates[$i],0,0,0,0,0,0,0
            ];
        }
        
        // read internet access statistics
        $this->iam_statistics = $toolbox->read_csv_array("../uploads/" . $client_id . "/iamStatistics.csv");
        
        $this->last_record = 0;
        $this->count_iam_records = count($this->iam_statistics);
        
        foreach ($this->iam_statistics as $iam_record) {
            $started = intval(substr($iam_record["started"], 0, 10));
            if ($started > $this->last_record)
                $this->last_record = $started;
            $duration_millis = intval($iam_record["durationMillis"]);
            $chart_record_index = intval(($now - $started) / $secday) + 1;
            if (($chart_record_index >= 0) && ($chart_record_index < $count_of_days)) {
                $iam_column = (isset($iam_columns[$iam_record["result"]])) ? $iam_columns[$iam_record["result"]] : $iam_columns["other"];
                // accumulate time and together with the time count the times accumulated 
                $this->container_duration_chart_data[$chart_record_index][$iam_column] += $duration_millis;
                $this->container_count_chart_data[$chart_record_index][$iam_column] ++;
            }
        }
        
        // read transaction queue statistics
        $this->txq_statistics = $toolbox->read_csv_array("../uploads/" . $client_id . "/txqStatistics.csv");
        $this->count_txq_records = count($this->txq_statistics);
        foreach ($this->txq_statistics as $txq_record) {
            $started = intval(substr($txq_record["created"], 0, 10));
            if ($started > $this->last_record)
                $this->last_record = $started;
            $waited_millis = intval($txq_record["waitedMillis"]);
            $chart_record_index = intval(($now - $started) / $secday) + 1;
            if (($chart_record_index >= 0) && ($chart_record_index < $count_of_days)) {
                $txq_column = (isset($txq_columns[$txq_record["type"]])) ? $txq_columns[$txq_record["type"]] : $txq_columns["other"];
                // accumulate time and together with the time count the times accumulated
                $this->transactions_wait_time_chart_data[$chart_record_index][$txq_column] += $waited_millis;
                $this->transactions_count_chart_data[$chart_record_index][$txq_column] ++;
            }
        }
        
        // calculate the average of time values by simple division per data point
        for ($i = 1; $i < (1 + $count_of_days); $i ++) {
            for ($j = 1; $j < 3; $j ++)
                if ($this->container_count_chart_data[$i][$j] > 0)
                    $this->container_duration_chart_data[$i][$j] = $this->container_duration_chart_data[$i][$j] /
                             $this->container_count_chart_data[$i][$j];
            for ($j = 1; $j < 7; $j ++)
                if ($this->transactions_count_chart_data[$i][$j] > 0)
                    $this->transactions_wait_time_chart_data[$i][$j] = $this->transactions_wait_time_chart_data[$i][$j] /
                             $this->transactions_count_chart_data[$i][$j];
        }
    }

    /**
     * Construct the instance. This loads all data for the statistics immediately
     *
     * @param Tfyh_toolbox $toolbox
     *            The toolbox for the csv handling functions.
     * @param int $client_id
     *            The efaCloudUserID of the client, for which the statistics are gathered
     */
    function __construct (Tfyh_toolbox $toolbox, int $client_id)
    {
        $this->read_statistics($toolbox, $client_id, 14);
    }

    /**
     * Little helper to format the data output for javascript-type propagation to client.
     *
     * @param array $data_array
     *            array to be formatted. First column must be a String, all others numbers
     * @return string the firmatted array
     */
    private function format_array (array $data_array)
    {
        $ret = "[";
        for ($r = 0; $r < count($data_array); $r ++) {
            $ret .= "[";
            for ($c = 0; $c < count($data_array[$r]); $c ++) {
                if (($c == 0) || ($r == 0))
                    $ret .= "'" . $data_array[$r][$c] . "',";
                else
                    $ret .= strval(round($data_array[$r][$c], 1)) . ",";
            }
            $ret = substr($ret, 0, strlen($ret) - 1) . "],\n";
        }
        $ret = substr($ret, 0, strlen($ret) - 2) . "]";
        return $ret;
    }

    /**
     * Simple getter
     *
     * @return string the formatted data for the google charts javascript array
     */
    public function get_container_duration_chart_data ()
    {
        return $this->format_array($this->container_duration_chart_data);
    }

    /**
     * Simple getter
     *
     * @return string the formatted data for the google charts javascript array
     */
    public function get_container_count_chart_data ()
    {
        return $this->format_array($this->container_count_chart_data);
    }

    /**
     * Simple getter
     *
     * @return string the formatted data for the google charts javascript array
     */
    public function get_transactions_wait_time_chart_data ()
    {
        return $this->format_array($this->transactions_wait_time_chart_data);
    }

    /**
     * Simple getter
     *
     * @return string the formatted data for the google charts javascript array
     */
    public function get_transactions_count_chart_data ()
    {
        return $this->format_array($this->transactions_count_chart_data);
    }
}