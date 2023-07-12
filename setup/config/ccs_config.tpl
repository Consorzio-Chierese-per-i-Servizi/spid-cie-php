<?php
/*
 * The configuration of SimpleSAMLphp
 *
 */

$config = array(
    /*
     * Database
     *
     * Database in which to track CIE access
     */

    /*
     * Database connection string.
     * Ensure that you have the required PDO database driver installed
     * for your connection string.
     */
    'database.dsn' => {{TRACKINGDBDNS}},

    /*
     * SQL database credentials
     */
    'database.username' => {{TRACKINGDBUSERNAME}},
    'database.password' => {{TRACKINGDBPASSWORD}},
);
