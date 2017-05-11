<?php
	include('inc/vars.php');
	define('IMPORT_DIR', './import/');
	define('SKIP', 'skip');
	define('HIGH_ERRORS', 1000);
	define('MID_ERRORS', 100);
	define('LOW_ERRORS', 50);
	define('DAYS_SHOW', 5); // How many days do we want to show? 5 is seven :)
?>
<html>
<head>
<meta charset="UTF-8" />
<link rel="icon" type="image/jpeg" href="img/fttb.jpg" />
<link rel="stylesheet" type="text/css" href="css/main.css">
</head>
<body>
<div class="martin"><div onclick='document.getElementById("martin").style.display="block"'>名人</div><img id="martin" src="img/Martin.png" alt="Coach" onclick='document.getElementById("martin").style.display="none"' /></div>
<?php
	if(isset($_GET['mode']))
		$mode = $_GET['mode'];
	else
		$mode = '';

	$mysqli = new mysqli("localhost", "fttb", "123654", "fttb");
	if ($mysqli->connect_errno) {
		echo "Can't connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error;
	}
	$mysqli->set_charset("utf8");

	// input or change the address
	if(isset($_POST['addr']) && isset($_POST['addr_id']) && isset($_POST['str']) && isset($_POST['host_id'])) {
		$addr_id = $_POST['addr_id'];
		unset($_POST['addr_id']);
		$host_id = $_POST['host_id'];
		$str = $mysqli->real_escape_string($_POST['str']);
		if($host_id > 0 && $str !== '') {
			if($addr_id > 0) { // edit
				//$query = 'UPDATE addr SET str = ("'.$str.'") WHERE id = '.$addr_id;
				$query = 'INSERT INTO addr (str) VALUES ("'.$str.'") ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)';
				if (!($result = $mysqli->query($query))) echo "50: ".$mysqli->error;
				$addr_id = $mysqli->insert_id;
			}
			else { // new
				$query = 'INSERT INTO addr (str) VALUES ("'.$str.'") ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)';
				if (!($result = $mysqli->query($query))) echo "55: ".$mysqli->error;
				$addr_id = $mysqli->insert_id;
			}
			$query = 'UPDATE host SET addr_id = '.$addr_id.' WHERE id ='.$host_id;
			if (!($result = $mysqli->query($query))) echo "59: ".$mysqli->error;
		}
	}

	// change the host’s comment
	if(isset($_POST['comm']) && isset($_POST['port_id']) && isset($_POST['comment'])) {
		$comment = $mysqli->real_escape_string($_POST['comment']);
		$port_id = $_POST['port_id'];
		unset($_POST['port_id']);
		if($port_id > 0 && $comment !== '') {
			$query = 'UPDATE port SET comment = "'.$comment.'" WHERE id ='.$port_id;
			if (!($result = $mysqli->query($query))) echo "55: ".$mysqli->error;
		}
	}

	// Import e-mail to mysql
	if ($handle = opendir(IMPORT_DIR)) {
		while (false !== ($entry = readdir($handle))) {
			if ($entry != "." && $entry != "..") {
				$file = IMPORT_DIR.$entry;
				$fdata = FALSE;
				$handle2 = @fopen($file, "r");
				if ($handle2) {
					$text = '';
				    while (($buffer = fgets($handle2)) !== false) {
						$utf8 = mb_convert_encoding($buffer, "utf-8", "windows-1251");
						if($fdata)
							$text .= $utf8;
						if($buffer == "\r\n")
							$fdata = TRUE;
				    }
				    if (!feof($handle2)) {
				        echo "Error: unexpected fgets() fail\n";
				    }
				    fclose($handle2);
					unlink($file);
				}
				break;
		    }
	    }
	    closedir($handle);
	}
	if (isset($text) && strlen($text) > 0) {
		$strs = explode("\n", str_replace("\r", "", $text));
		foreach($strs as $key => $val) {
			if((strlen($val) > 0)) {
				if(($val[0] == '+')) {
					$tmp = preg_split('/( ошибок )|( \()/', $val);	// Get host and port
					$cols = explode(' ', $tmp[1], 2);				// Split to host and port

					// Hosts w/o dublicate
					$query = 'INSERT INTO host (host) VALUES ("'.$cols[0].'") ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)';
					if (!($result = $mysqli->query($query))) echo $mysqli->error;
					$host_id = $mysqli->insert_id;

					// Ports w/o dublicate
					$query = 'INSERT INTO port (port, host_id) VALUES ("'.$cols[1].'", '.$host_id.') ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)';
					if (!($result = $mysqli->query($query))) echo $mysqli->error;
					$port_id = $mysqli->insert_id;
				}
				else {
					$cols = explode(" ", $val);
					if($cols[5][0] == "+") {

						// Counters
						$query = 'INSERT INTO error (date, count, port_id) VALUES ("'.$cols[3].'", 0'.$cols[5].', '.$port_id.') ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)';
						if (!($result = $mysqli->query($query))) echo $mysqli->error;
						$error_id = $mysqli->insert_id;
					}

				}
			}
		}
	}

	switch($mode) {
	case 'export': // Export to pretty view
		echo '<div class="menu"><a href="?mode=normal">Normal<img src="img/table.png" alt="Normal"></a></a></div>';
		$query = 'SELECT host_id, host, port_id, port, date, count, str, comment FROM host, port, error, addr WHERE host_id = host.id AND port_id = port.id AND addr_id = addr.id ORDER BY date DESC, count DESC, host, port';
		if ($result = $mysqli->query($query)) {
			$lastdate = '';
			$i = 1;
			$dcount = DAYS_SHOW;
			while ($row = $result->fetch_assoc()) {
				if($i > 1 && $lastdate !== $row['date']) {
					$i = 1;
					if($dcount-- < 0) break;
					echo "<hr />\n";
				}
				if($i == 1) echo "<h1>".$row["date"]."</h1>";
				if(!stripos($row["comment"], SKIP)) {
					echo '<p><div class="addr">'.$row['str'].'</div>';
					if($row['count'] > HIGH_ERRORS)
						echo '<span class="bigerr">'.declOfNum($row['count'], array(' ошибка ',' ошибки ',' ошибок ')).'</span> ';
					else 
						if($row['count'] < MID_ERRORS)
							echo '<span class="lowerr">'.declOfNum($row['count'], array(' ошибка ',' ошибки ',' ошибок ')).'</span> ';
						else
							echo declOfNum($row['count'], array(' ошибка ',' ошибки ',' ошибок '));
					
					echo $row['host'].' '.$row['port'].'<br />';
					echo str_replace("\n", "<br />\n", $row['comment'])."</p>\n";
				}
				$lastdate = $row['date'];
				$i++;

			}
		}
		break;
	case 'import':
?>
<div style="float:right">
<form method="post" action="">
	<textarea name="text" rows="10" cols="80"></textarea><br />
	<input type="submit" /><br />
</form>
</div>
<?php
	default: // Normal data output
		echo '<div class="menu"><a href="?mode=export">Export<img src="img/export.png" alt="Export"></a>|<a href="">Refresh<img src="img/refresh.png" alt="Refresh"></a></div>';
		$query = 'SELECT host_id, host, port_id, port, date, count, addr_id, comment FROM host, port, error WHERE host_id = host.id AND port_id = port.id ORDER BY date DESC, count DESC, host, port';
		if ($result = $mysqli->query($query)) {
			$i = 0;
			$lastdate = '';
			$actual[] = -1;
			$dcount = DAYS_SHOW;
			while ($row = $result->fetch_assoc()) {
				if($i < 1) {
					echo "<h1>".$row["date"]."</h1><table>";
					$i = 1;
				}
				else if($lastdate !== $row["date"]) {
					$actual[0] = 1;
					echo "</table>\n";
					if($dcount-- < 0) break;
					echo "<h1>".$row["date"]."</h1><table>\n";
					$i = 1;

				}
				if($row["count"] > LOW_ERRORS && (!stripos($row["comment"], SKIP) || isset($_GET['admin']))) { // lowest error’s threshold
					if($actual[0] < 0) $actual[] = $row["port_id"];
					if($actual[0] > 0) {
						if($key = array_search($row["port_id"], $actual))
							echo '<tr class="err">';
						else echo '<tr class="fix">';
						echo '<td class="id">'.$i.'</td><td class="host">';
					}
					else echo '<tr class="new">';

					$addr = array();
					if($row["addr_id"] > 0) {
						$query2 = 'SELECT str FROM addr WHERE id = '.$row["addr_id"];
						if ($result2 = $mysqli->query($query2))
							$addr = $result2->fetch_assoc();
						$result2->close();
					}

					// historical errors
					$query3 = 'SELECT date, count FROM error WHERE date < "'.$row['date'].'" AND port_id = '.$row['port_id'].' ORDER BY date DESC';
					if ($result3 = $mysqli->query($query3)) {
						$date3 = $row['date'];
						$texterr = $row['count'].'<span class="prev">';
						$done = FALSE;
						$i3 = 3;
						while (!$done && $i3 > 0 && $row3 = $result3->fetch_assoc()) {
							$datetime1 = new DateTime($date3);
							$datetime2 = new DateTime($row3['date']);
							$interval = $datetime1->diff($datetime2)->days;
							switch($interval) {
							case 3:
								$date3 = $row3['date'];
								$texterr .= '<br />—<br />—';
								$done = TRUE;
								break;
							case 2:
								$date3 = $row3['date'];
								$texterr .= '<br />—<br />'.$row3['count'];;
								$done = TRUE;
								break;
							case 1:
								$date3 = $row3['date'];
								$texterr .= '<br />'.$row3['count'];
							}
							$i3--;
						}
						$texterr .= '</span>';
					}
					$result3->close();


					if($actual[0] < 0) echo '<td class="id">'.$i.'</td><td class="host">';
					else '<td class="num"></td><td class="host">';
					echo '<a href="'.FTTB_HOST_SEARCH_URL.$row["host"].'" target="_blank">';
					echo $row["host"].'</a> <div class="port">'.$row["port"].'</div></td><td class="'.($row["count"] > HIGH_ERRORS ? 'big' : '').' num">'.$texterr.'</td>';

					// addresses
					echo '<form id="form'.$row["addr_id"].'" method="post" action=""><input type="hidden" name="addr" value="1" /><input type="hidden" name="addr_id" value="'.$row["addr_id"].'" /><input type="hidden" name="host_id" value="'.$row["host_id"].'" /><td class="str">';

					if(isset($_POST['addr_id']) && $row["addr_id"] == $_POST['addr_id'])
						echo '<a name="addr'.$row["addr_id"].'">&nbsp;</a><input type="text" name="str" value="'.(isset($addr["str"]) ? $addr["str"] : '').'" />';
					else
						echo isset($addr["str"]) ? $addr["str"] : '';
					if(isset($_GET['admin'])) echo '<input type="image" src="img/pencil.png" onclick="document.getElementById(\'form'.$row["addr_id"].'\').action=\'#addr'.$row["addr_id"].'\';" />';
					echo '</td></form>';

					// comments
					echo '<form id="text'.$row["port_id"].'" method="post" action=""><input type="hidden" name="comm" value="1" /><input type="hidden" name="port_id" value="'.$row["port_id"].'" /><td class="comment">';

					echo '<input type="image" src="img/pencil.png" onclick="document.getElementById(\'text'.$row["port_id"].'\').action=\'#port'.$row["port_id"].'\';" />';

					if(isset($_POST['port_id']) && $row["port_id"] == $_POST['port_id'])
						echo '<a name="port'.$row["port_id"].'">&nbsp;</a><textarea rows="10" name="comment" maxlength="1024">'.$row["comment"].'</textarea>';
					else
						echo '<pre>'.$row["comment"].'</pre>';
					echo '</td></form>';

					echo "</tr>\n";
					$i++;
				}
				$lastdate = $row["date"];
			}
			$result->close();
		}
		// End normal data output
	} 
	$mysqli->close();


	/** 
	 * Функция склонения числительных в русском языке 
	 * 
	 * @param int      $number  Число которое нужно просклонять 
	 * @param array  $titles      Массив слов для склонения 
	 * @return string
	 **/
	function declOfNum($number, $titles)
	{
		$cases = array (2, 0, 1, 1, 1, 2);
		return $number." ".$titles[ ($number%100 > 4 && $number %100 < 20) ? 2 : $cases[min($number%10, 5)] ];
	}

?>
</body>
</html>
