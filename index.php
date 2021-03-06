<?php
require 'includes/mysql_login.php';
require 'includes/config.php';
require 'includes/functions.php';
session_start();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">

<?php
	echo "<title>".$WebsiteTitle."</title>"
?>

<link rel="icon" href="favicon.png" type="image/png" sizes="16x16">
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
<link href="/stylesheet.css" type="text/css" rel="stylesheet" />

<?php
	if($EnableRedirectionToHttps)
	{
		require 'includes/redirect_to_https.html';
	}
?>


</head>

<body background="background.png">

<div class="container">

	<form method="POST" id="loginform">

	<input id="passwordTextBox" type="password" name="password"></input>

	<button id="btn" id="loginbutton" name="submit">Login</button>

	<br>

	

<br>

<?php
	//echo '<iframe width="0" height="0" src="'.$WebDomain.'/files/webui.mp3" frameborder="0" allowfullscreen></iframe>';

	
	/*
	Reikalingas DB:
	Pavadinimas: serverBadLogins
	id - int
	ip - varchar
	tries - int
	banned - bit
	*/
	CheckSessionLoggings();
	// if(!isset($_SESSION['status']))
	// {
	// 	$_SESSION['status'] = null;
	// }
	if($_SESSION['status'] == "admin" || $_SESSION['status'] == "superadmin")
	{
		echo "<meta http-equiv='refresh' content='1; url=".$WebDomain."/adminpanel' />";
	}

	if(isset($_POST['submit']))
	{
		echo "<div id='loginAlertText'>";
		$userIP = $_SERVER['REMOTE_ADDR'];
		$currentTime = date("Y-m-d H:i:s");
		//echo $currentTime."<br>";
		//$currentTime = date("Y-m-d H:i:s", strtotime($currentTime.'+ '.$BanLength.' hours'));
		//echo $currentTime."<br>";

		//Tikrinimas ar IP nėra užblokuotas. Jeigu jis užblokuotas, neleidžia vykdyti prisijungimo proceso.
		$bannedIp = false;
		$sqlRead = "select * from serverBadLogins";
		$result = mysqli_query($conn, $sqlRead);
		$bannedTillDate = null;
		if (mysqli_num_rows($result) > 0) 
		{
			while($row = mysqli_fetch_assoc($result))
			{	
				if($row['bannedTill'] > $currentTime && $userIP == $row['ip'])
				{
					$bannedTillDate = $row['bannedTill'];
					$bannedIp = true;
				}
			}
		}
		if($bannedIp)
		{
			$_SESSION['nick'] = "unlogged";
			$_SESSION['status'] = "user";
			echo "<font color='red' size='5'><b>Your IP has been banned from the system till<br>".$bannedTillDate."</b></font><br>";
		}
		//Jeigu IP neužbanintas, vykdo prisijungimo procesą.
		else
		{
			$didILogIn = false;
			//Jeigu IP atitinka arba suvesti geri prisijungimo duomenis, uždeda session['status'] = admin arba superadmin;
			$sqlCheckAdmins = "select * from serverAdmins";
			$sqlResultsCheckAdmins = mysqli_query($conn, $sqlCheckAdmins);
			if (mysqli_num_rows($sqlResultsCheckAdmins) > 0) 
			{
				while($row = mysqli_fetch_assoc($sqlResultsCheckAdmins))
				{	
					if($row['ip'] == $_SERVER['REMOTE_ADDR'])
					{
						$_SESSION['nick'] = $row['nick'];
						//$_SESSION['status'] = $row['status']; //Sitas bus uzdedamas per funkcija.
						$_SESSION['password'] = $row['password'];
						$didILogIn = true;
						break;
					}
					$saltedPassword = $RandomSalt1.$_POST['password'].$RandomSalt2;
					$saltedPassword = hash('sha512', $saltedPassword);
					if($saltedPassword == $row['password'])
					{
						$_SESSION['nick'] = $row['nick'];
						//$_SESSION['status'] = $row['status']; //Sitas bus uzdedamas per funkcija.
						$_SESSION['password'] = $saltedPassword;
						$didILogIn = true;
						break;
					}
				}
			}


			if($didILogIn)
			{
				echo "<font color='red' size='5'><b>Succesfully connected"."</b></font><br>";

				//Jeigu IP buvo gaves ispejimu del blogu prisijungimu, juos istrins.
				$sqlDelete = "delete from serverBadLogins where ip='$userIP'";
				mysqli_query($conn, $sqlDelete);

				header('Location: /adminpanel');
			}
			//Jeigu suvesti blogi prisijungimai, į database įkelia blogą prisijungimą.
			else
			{
				//Scriptas, kuris neleis iš to pačio IP rašyti daug prisijungimų. Uždėtas limitas.
				$userPassword = $_POST['password'];
				if($userPassword != null)
				{	
					$tries = 1;
					$sqlRead = "select * from serverBadLogins";
					$doesTheIPExist = false;
					$result = mysqli_query($conn, $sqlRead);
					if (mysqli_num_rows($result) > 0) 
					{
						while($row = mysqli_fetch_assoc($result))
						{
							if($userIP == $row['ip'])
							{
								$tries = $row['tries'];
								$tries++;
								$doesTheIPExist = true;
								break;
							}
						}
					}
					if($doesTheIPExist)
					{
						if($tries >= $MaximumTriesWhileLogging)
						{
							$newBanDate = date("Y-m-d H:i:s", strtotime($currentTime.'+ '.$BanLength.' minutes'));
							$sqlUpdate = "update serverBadLogins SET tries='$tries', bannedTill='$newBanDate', lastLogin='$currentTime' where ip='$userIP'";
							//Logu struktura: "User (IP) did ..."
							$logText = "User (".$userIP.") just got banned until ".$newBanDate;
							AddToLogs($logText);
							echo "<font color='red' size='5'><b>You have been banned!</b></font><br>";
						}
						else
						{
							//Logu struktura: "User (IP) did ..."
							$logText = "User (".$userIP.") tried to connect with incorrect logins into system. He used his ".$tries." tries";
							AddToLogs($logText);
							$sqlUpdate = "update serverBadLogins SET tries='$tries', lastLogin='$currentTime' where ip='$userIP'";
						}
						mysqli_query($conn, $sqlUpdate);
					}
					else
					{
						$sqlInsert = "insert into serverBadLogins(ip, tries, lastLogin) VALUES ('$userIP','$tries', '$currentTime')";
						mysqli_query($conn, $sqlInsert);
						//Logu struktura: "User (IP) did ..."
						$logText = "User (".$userIP.") tried to connect with incorrect logins into system. He used his ".$tries." tries";
						AddToLogs($logText);
					}
					echo "<font color='red' size='5'><b>You weren't connected to the system. You used $tries of ".$MaximumTriesWhileLogging." attempts."."</b></font><br>";
					$_SESSION['status'] = "user";
					$_SESSION['nick'] = "unlogged";
				}
				else
				{
					echo "<font color='red' size='5'><b>You didn't write any data</b></font><br>";
					//Logu struktura: "User (IP) did ..."
					$logText = "User (".$userIP.") tried to connect without writing any data";
					AddToLogs($logText);
					$_SESSION['status'] = "user";
					$_SESSION['nick'] = "unlogged";
				}
			}
		}
		echo "</div>";
		mysqli_close($conn);
	}
?>

</form>

</div>

</body>

</html>