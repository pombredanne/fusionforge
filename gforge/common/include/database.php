<?php
/**
 *
 * database.php - The database abstraction library
 * This is the PostgreSQL version of our database connection/querying layer
 *
 * SourceForge: Breaking Down the Barriers to Open Source Development
 * Copyright 1999-2001 (c) VA Linux Systems
 * http://sourceforge.net
 *
 * @version   $Id$
 *
 */

//$conn - database connection handle

/**
 * Current row for each result set
 *
 * @var			array	$sys_db_row_pointer
 */
$sys_db_row_pointer=array(); //current row for each result set


/**
 *  db_connect() - Connect to the database
 *  Notice the global vars that must be set up
 *  Sets up a global $conn variable which is used 
 *  in other functions in this library.
 */
function db_connect() {
	global $sys_dbhost,$sys_dbuser,$sys_dbpasswd,$conn,
		$sys_dbname,$sys_db_use_replication,$sys_dbreaddb,$sys_dbreadhost;

	//
	//	Connect to primary database
	//
	$conn = @pg_pconnect("user=$sys_dbuser dbname=$sys_dbname host=$sys_dbhost password=$sys_dbpasswd"); 

	//
	//	If any replication is configured, connect
	//
	if ($sys_db_use_replication) {
		$conn2 = @pg_pconnect("user=$sys_dbuser dbname=$sys_dbreaddb host=$sys_dbreadhost password=$sys_dbpasswd"); 
	} else {
		$conn2 = $conn;
	}

	//
	//	Now map the physical database connections to the
	//	"virtual" list that is used to distribute load in db_query()
	//
	define(SYS_DB_PRIMARY,$conn);
	define(SYS_DB_STATS,$conn2);
	define(SYS_DB_TROVE,$conn2);
	define(SYS_DB_SEARCH,$conn2);

	// Register top-level "finally" handler to abort current
	// transaction in case of error
	register_shutdown_function("system_cleanup");
}

/**
 *  db_query() - Query the database.
 *
 *  @param text SQL statement.
 *  @param int How many rows do you want returned.
 *  @param int Of matching rows, return only rows starting here.
 *	@param int ability to spread load to multiple db servers.
 *	@return int result set handle.
 */
function db_query($qstring,$limit='-1',$offset=0,$dbserver=SYS_DB_PRIMARY) {
	global $QUERY_COUNT;
	$QUERY_COUNT++;

	if ($limit > 0) {
		if (!$offset || $offset < 0) {
			$offset=0;
		}
		$qstring=$qstring." LIMIT $limit OFFSET $offset";
	}

	$GLOBALS['G_DEBUGQUERY'] .= $qstring .' |<font size="-2">'.$dbserver.'</font>'. "<P>\n";
	return @pg_exec($dbserver,$qstring);
}

/* Current transaction level, private variable */
/* FIXME: Having scalar variable for transaction level is
   no longer correct after multiple database (dbservers) support
   introduction. However, it is true that in one given PHP
   script, at most one db is modified, so this works for now. */
$_sys_db_transaction_level = 0;

/**
 *	db_begin() - Begin a transaction.
 *
 *  @param		constant		Database server (SYS_DB_PRIMARY, SYS_DB_STATS, SYS_DB_TROVE, SYS_DB_SEARCH)
 *	@return true.
 */
function db_begin($dbserver=SYS_DB_PRIMARY) {
	global $_sys_db_transaction_level;

	// start database transaction only for the top-level
	// programmatical transaction
	$_sys_db_transaction_level++;
	if ($_sys_db_transaction_level == 1) {
		return db_query("BEGIN WORK", -1, 0, $dbserver);
	}

	return true;
}

/**
 *	db_commit() - Commit a transaction.
 *
 *  @param		constant		Database server (SYS_DB_PRIMARY, SYS_DB_STATS, SYS_DB_TROVE, SYS_DB_SEARCH)
 *	@return true on success/false on failure.
 */
function db_commit($dbserver=SYS_DB_PRIMARY) {
	global $_sys_db_transaction_level;

	// check for transaction stack underflow
	if ($_sys_db_transaction_level == 0) {
		echo "COMMIT underflow<br>";
		return false;
	}

	// commit database transaction only when top-level
	// programmatical transaction ends
	$_sys_db_transaction_level--;
	if ($_sys_db_transaction_level == 0) {
		return db_query("COMMIT", -1, 0, $dbserver);
	}

	return true;
}

/**
 *	db_rollback() - Rollback a transaction.
 *
 *  @param		constant		Database server (SYS_DB_PRIMARY, SYS_DB_STATS, SYS_DB_TROVE, SYS_DB_SEARCH)
 *	@return true on success/false on failure.
 */
function db_rollback($dbserver=SYS_DB_PRIMARY) {
	global $_sys_db_transaction_level;

	// check for transaction stack underflow
	if ($_sys_db_transaction_level == 0) {
		echo "ROLLBACK underflow<br>";
		return false;
	}

	// rollback database transaction only when top-level
	// programmatical transaction ends
	$_sys_db_transaction_level--;
	if ($_sys_db_transaction_level == 0) {
		return db_query("ROLLBACK", -1, 0, $dbserver);
	}

	return true;
}

/**
 *	db_numrows() - Returns the number of rows in this result set.
 *
 *	@param		int		Query result set handle.
 *	@return int number of rows.
 */

function db_numrows($qhandle) {
	return @pg_numrows($qhandle);
}

/**
 *  db_free_result() - Frees a database result properly.
 *
 *	@param		int		Query result set handle.
 */
function db_free_result($qhandle) {
	return @pg_freeresult($qhandle);
}

/**
 *  db_reset_result() - Reset is useful for db_fetch_array
 *  sometimes you need to start over.
 *
 *	@param		int		Query result set handle.
 *  @param		integer	Row number.
 *	@return int row.
 */
function db_reset_result($qhandle,$row=0) {
	global $sys_db_row_pointer;
	return $sys_db_row_pointer[$qhandle]=$row;
}

/**
 *  db_result() - Returns a field from a result set.
 *
 *	@param		int		Query result set handle.
 *  @param		integer Row number.
 *  @param		string	Field name.
 *	@return contents of field from database.
 */
function db_result($qhandle,$row,$field) {
	return @pg_result($qhandle,$row,$field);
}

/**
 *  db_numfields() - Returns the number of fields in this result set.
 *
 *	@param		int		Query result set handle.
 */
function db_numfields($lhandle) {
	return @pg_numfields($lhandle);
}

/**
 *  db_fieldname() - Returns the number of rows changed in the last query.
 *
 *	@param		int		Query result set handle.
 *  @param		int		Column number.
 *	@return text name of the field.
 */
function db_fieldname($lhandle,$fnumber) {
	return @pg_fieldname($lhandle,$fnumber);
}

/**
 *  db_affected_rows() - Returns the number of rows changed in the last query.
 *
 *	@param		int		Query result set handle.
 *	@return int number of affected rows.
 */
function db_affected_rows($qhandle) {
	return @pg_cmdtuples($qhandle);
}

/**
 *  db_fetch_array() - Returns an associative array from 
 *  the current row of this database result
 *  Use db_reset_result to seek a particular row.
 *
 *	@param		int		Query result set handle.
 *	@return associative array of fieldname/value key pairs.
 */
function db_fetch_array($qhandle) {
	global $sys_db_row_pointer;
	$sys_db_row_pointer[$qhandle]++;
	return @pg_fetch_array($qhandle,($sys_db_row_pointer[$qhandle]-1));
}

/**
 *  db_insertid() - Returns the last primary key from an insert.
 *
 *	@param		int		Query result set handle.
 *  @param		string	table_name is the name of the table you inserted into.
 *  @param		string	pkey_field_name is the field name of the primary key.
 *  @param		string	Server to which original query was made
 *	@return int id of the primary key or 0 on failure.
 */
function db_insertid($qhandle,$table_name,$pkey_field_name,$dbserver=SYS_DB_PRIMARY) {
	$oid=@pg_getlastoid($qhandle);
	if ($oid) {
		$sql="SELECT $pkey_field_name AS id FROM $table_name WHERE oid='$oid'";
		//echo $sql;
		$res=db_query($sql, -1, 0, $dbserver);
		if (db_numrows($res) >0) {
			return db_result($res,0,'id');
		} else {
		//	echo "No Rows Matched";
		//	echo db_error();
			return 0;
		}
	} else {
//		echo "No OID";
//		echo db_error();
		return 0;
	}
}

/**
 *  db_error() - Returns the last error from the database.
 *
 *  @param		constant		Database server (SYS_DB_PRIMARY, SYS_DB_STATS, SYS_DB_TROVE, SYS_DB_SEARCH)
 *	@return text error message.
 */
function db_error($dbserver=SYS_DB_PRIMARY) {
	return @pg_errormessage($dbserver);
}

/**
 *	system_cleanup() - In the future, we may wish to do a number 
 *	of cleanup functions at script termination.
 *
 *	For now, we just abort any in-process transaction.
 */
function system_cleanup() {
	global $_sys_db_transaction_level;
	if ($_sys_db_transaction_level > 0) {
		echo "Open transaction detected!!!";
		db_query("ROLLBACK");
	}
}

function db_drop_table_if_exists ($tn) {
	$sql = "SELECT COUNT(*) FROM pg_class WHERE relname='$tn';";
	$rel = db_query($sql);
	echo db_error();
	$count = db_result($rel,0,0);
	if ($count != 0) {
		$sql = "DROP TABLE $tn;";
		$rel = db_query ($sql);
		echo db_error();
	}
}

function db_drop_sequence_if_exists ($tn) {
	$sql = "SELECT COUNT(*) FROM pg_class WHERE relname='$tn';";
	$rel = db_query($sql);
	echo db_error();
	$count = db_result($rel,0,0);
	if ($count != 0) {
		$sql = "DROP SEQUENCE $tn;";
		$rel = db_query ($sql);
		echo db_error();
	}
}

?>
