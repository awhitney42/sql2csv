<?php

require_once 'DB.php';

error_reporting(E_ALL);
ini_set('display_errors', true);
ini_set('auto_detect_line_endings', true);
ini_set('default_socket_timeout', 600);
ini_set('mssql.connect_timeout', 600);
ini_set('mssql.timeout', 600);

// Allow only invocation from the command-line.
if (php_sapi_name() !== 'cli') {
    header("HTTP/1.0 403 Forbidden");
    exit(1);
}

// Check argument count and display usage message if the count isn't acceptable.
if (count($argv) < 3) {
    print "\nusage: sql2csv.php <dsn> <input file or directory>\n\n";
    print "example: sql2csv.php <driver>://<username>:<password>@<host>:<port>/<database> data.sql\n";
    print "\nThis script queries the database specified by the <dsn> paramater with the SQL in the input file. If the second parameter is a directory, then any file with an 'sql' extension within that directory will be used as input files.\n\n";
    exit;
}

// Retrieve and store command-line arguments.
$dsn = $argv[1];
$input = $argv[2];

$db = DB::connect($dsn);

if (DB::isError($db)) {
    print "Could not establish DB connection.\n";
    exit;
}

// Get a list of the input files.
if (is_dir($input)) {
    $input = rtrim( $input, '/' ).'/'; // Normalize directory path.
    $files = Array();
    $dh = opendir($input);
    if ($dh) {
        while (($file = readdir($dh)) !== false) {
            if (filetype($input . $file) != "dir") {
                $ext = pathinfo($input . $file, PATHINFO_EXTENSION);
                if (strtolower($ext) == "sql") {
                    $files[] = $input . $file;
                }
            }
        }
        closedir($dh);
    } else {
        print "Error: unable to open directory " . $input . "\n";
        exit(1);
    }

} else {
    $files = Array($input);
}

// Iterate through each input file to read and execute the SQL and write the output to CSV.
foreach ($files as $file) {

    $csv_filename = $file . ".csv";

    $sql = file_get_contents($file);

    query_to_csv($db, $sql, $csv_filename);

}

exit(0);

# ---

function query_to_csv($db_conn, $query, $filename, $attachment = false, $headers = true) {
     
    if($attachment) {
        // send response headers to the browser
        header( 'Content-Type: text/csv' );
        header( 'Content-Disposition: attachment;filename='.$filename);
        $fp = fopen('php://output', 'w');
    } else {
        $fp = fopen($filename, 'w');
    }
    print $query . "\n";
     
    $result = $db_conn->getAll($query, DB_FETCHMODE_ASSOC);
    if (DB::isError($result)) {
        print "DB error in " . __FUNCTION__ . "\n\n";
        print_r($result);
        exit();
    }

    foreach ($result as $row) {
        if($headers) {
            // output header row (if at least one row exists)
            fputcsv($fp, array_keys($row));
            $headers = false;
        }
        fputcsv($fp, $row);
    }
     
    fclose($fp);
}

exit(0);

// end of file sql2.csv.php