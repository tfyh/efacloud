<?php
   if (isset($_GET["session"]) && (intval($_GET["session"]) == 1))
     session_start();
   echo "Header in request (as received @server):\n";
   foreach (apache_request_headers() as $key => $value)
      echo "  ". $key . " = " . $value . "\n";
   echo "  Get parameters:\n";
   foreach ($_GET as $key => $value)
      echo "  ". $key . " = " . $value . "\n";
   echo "  Posted values:\n";
   foreach ($_POST as $key => $value)
      echo "  ". $key . " = " . $value . "\n";
   if (isset($_GET["session"]) && (intval($_GET["session"]) == 1)) {
     echo "  Session started with ID: " . session_id() . "\n";
     echo "  Session parameters:\n";
     foreach ($_SESSION as $key => $value)
        echo "  ". $key . " = " . $value . "\n";
   } else {
        echo "  No session requested.";
   }     
