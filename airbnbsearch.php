<?php
include("RestRequest.inc.php");
include("connect_db.php");

define("SECS_PER_DAY", 86400);

$propDates = array();   // id => array(dates available)
$propNames = array();   // id => name
$availablePropNames = array();   // stores the list of properties available between a certain set of dates

// $verb can be GET or POST
function GetResponseDataFromURL($curlURL, $verb="GET")
{
    $req = new RestRequest($curlURL, $verb);
    $req->execute();
    $responseBody = json_decode($req->getResponseBody(), true);
    return $responseBody;
}

function ConstructQueryStr($location, $startDate, $endDate)
{
	return "https://www.airbnb.com/search/ajax_get_results?".
		"search_view=1&min_bedrooms=0&min_bathrooms=0&min_beds=0&page=1".
		"&location=".urlencode($location)."&checkin=".urlencode($startDate).
		"&checkout=".urlencode($endDate)."&guests=1&sort=0&keywords=&price_min=&price_max=&per_page=21";	
}

function AddPropertyAvailabilityToDB($cityName, $propId, $propName, $dateTimestamp)
{
	// two tables: properties(id, city, name) and available(id, dateTS)

	// Note that we store the city name with the property name in our own DB so that we can later search through 
	// the list of properties for any city without querying AirBNB
	$q1 = mysql_query("select name from properties where id=$propId");
	if ($q1 && mysql_num_rows($q1) > 0)
	{
		// there is already a record for this property, so update it
		mysql_query("update properties set cityName='".$cityName."', name='".$propName."' where id=$propId");
	}
	else
	{
		// insert a new record for this property
		mysql_query("insert into properties (id, city, name) VALUES ".
			"($propId, '".$cityName."', '".$propName."')");
	}

	// add a new entry for this date if it does not already exist
	$q2 = mysql_query("select dateTS from available where id=$propId");
	$alreadyExists = 0;
	while ($q2 && $rec = mysql_fetch_array($q2))
	{
		if ($rec["dateTS"] == $dateTimestamp)
		{
			$alreadyExists = 1;
			break;
		}
	}
	if (!$alreadyExists)
	{
		// this date has not been recorded for this property -- add it
		mysql_query("insert into available (id, dateTS) VALUES ($propId, $dateTimestamp)");
	}
}

$cityName = (isset($_GET["cityname"])? $_GET["cityname"] : "");

$cityNameLocal = (isset($_GET["citynameLocal"])? $_GET["citynameLocal"] : "");
$startDate = (isset($_GET["startDate"])? $_GET["startDate"] : "");
$endDate = (isset($_GET["endDate"])? $_GET["endDate"] : "");

//echo "city = ".$cityName;
if ($cityName != "")
{
	// search the AirBNB db for properties in this city for the next 7 days
	for ($i = 0; $i < 7; $i++)
	{
		// checkin and checkout dates cannot be the same
		$checkinDate = date("Y-m-d", time()+$i*SECS_PER_DAY);
		$checkoutDate = date("Y-m-d", time()+($i+1)*SECS_PER_DAY);
		$queryStr = ConstructQueryStr($cityName, $checkinDate, $checkoutDate);

		$responseBody = GetResponseDataFromURL($queryStr);

		// add to the associative array
		if (isset($responseBody["properties"]))
		{
			$properties = $responseBody["properties"];

			// this is an array -- take the name and id fields
			foreach ($properties as $prop)
			{
				$id = $prop["id"];
				$name = $prop["name"];
				$propNames[$id] = $name;

				// add this date to the array of dates for which this property is available
				if (!array_key_exists($id, $propDates))
				{
					$propDates[$id] = array();
				}
				array_push($propDates[$id], $i);

				// store in DB
				AddPropertyAvailabilityToDB($cityName, $id, $name, strtotime($checkinDate));
			}
		}
	}
}
else if ($cityNameLocal != "" && $startDate != "" && $endDate != "")
{
	// two tables: properties(id, city, name) and available(id, dateTS)
	$startTS = strtotime($startDate);
	$endTS = strtotime($endDate);
	$q3Str = "select name from properties p, available a where ".
		"p.id=a.id and p.city='".$cityNameLocal."' and a.dateTS>=$startTS and a.dateTS<=$endTS";
	//echo $q3Str;
	//echo "start=$startTS, end=$endTS";
	$q3 = mysql_query($q3Str);
	while ($q3 && $rec = mysql_fetch_array($q3))
	{
		array_push($availablePropNames, $rec["name"]);
	}
}
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title>AirBNB Search</title>	
</head>
<body>
    <FORM action="airbnb_TEST.php" method="get" enctype="text/plain">
    	Enter a city name: <input type="text" name="cityname">
    	<input type="submit" value="Submit">
    </form>

    <h2>OR</h2>

    <h3>The following search will look in the local db and return all properties available during those dates</h3>
    <FORM action="airbnb_TEST.php" method="get" enctype="text/plain">
    	Enter a city name: <input type="text" name="citynameLocal"><br>
    	Enter a start date (MM/DD/YYYY): <input type="text" name="startDate"><br>
    	Enter an end date (MM/DD/YYYY): <input type="text" name="endDate"><br>
    	<input type="submit" value="Submit">
    </form>
<?php
	if ($cityName != "")
	{
		// print out the table
?>
		<table border="1">
			<tr>
				<td></td>
<?php
	// print out the dates
	for ($i = 0; $i < 7; $i++)
	{
		// checkin and checkout dates cannot be the same
		$date = date("m/d", time()+$i*SECS_PER_DAY);
		echo "<td>".$date."</td>";
	}
?>
			</tr>
<?php
	// for each property, enter x in the appropriate columns
	foreach ($propDates as $propId => $datesArr)
	{
		echo "<tr>";

		// map the id to a name
		$propName = $propNames[$propId];
		echo "<td>".$propName."</td>";
		for ($i = 0; $i < 7; $i++)
		{
			// if this date occurs in the datesArr array, then put an x
			if (in_array($i, $datesArr))
			{
				echo "<td>x</td>";
			}
			else
			{
				echo "<td> </td>";
			}
		}

		echo "</tr>";
	}
?>

		</table>
<?php
	}
	else if ($cityNameLocal != "" && $startDate != "" && $endDate != "")
	{
		// local search
		echo "<h2>Properties available in $cityNameLocal between $startDate and $endDate:</h2>";
		foreach ($availablePropNames as $propName)
		{
			echo "<p>$propName</p>";
		}
	}
?>
</body>
