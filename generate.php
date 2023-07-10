<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ERROR);

set_time_limit(100000);

include_once 'my_db_con_1.php';
include_once 'pg_db_con_2.php';
include_once 'pg_db_con_3.php';
include_once 'pg_db_con_4.php';

$tempDir = dirname(__FILE__) . "/generateHailReport/tempReports/";
include_once dirname(__FILE__) . "/" . 'generateHailReport/userCheck.php';

if(isset($_GET['isDebug']) && $_GET['isDebug']==1){
	require_once("debug.php");
	$_POST = $postTemplate;
}

define('ABSPATH', dirname(__FILE__) . '/');
define('BASEPATH', rtrim(ABSPATH, '/'));
require(BASEPATH . '/includes/headerConn.php');
include( BASEPATH . '/updateWorkOrder.php' );

// POST parametrs from order_login_verification.php. Check and prepare data
$request = array();
$postData = $_POST['report_list'][0];

try {

    updateWorkOrdersStatus($ftsdb, 'in_progress', $_GET['id'], '');

    $request['atLocation'] = TRUE;
    if (isset($postData['search_date_start'])) {
      $dates = explode('/', $postData['search_date_start']);
      $request['dateFrom'] = date("m-d-y", strtotime($dates[1].'-'.$dates[0].'-'.$dates[2]));
    } else {
      die('Invalid From date');
    }
    if (isset($postData['search_date_end'])) {
        $dates = explode('/', $postData['search_date_end']);
        $request['dateTo'] = date("m-d-y", strtotime($dates[1].'-'.$dates[0].'-'.$dates[2]));
    } else {
      die('Invalid To date');
    }
    if (isset($postData['event_date'])) {
      $request['reportLossDate'] = $postData['event_date'];
    } else {
      die('Invalid To date');
    }

    if (isset($postData['sizeMin'])) {
      $request['sizeMin'] = $postData['sizeMin'];
    } else {
      $request['sizeMin'] = 0.75;
    }
    if (isset($postData['sizeMax'])) {
      $request['sizeMax'] = $postData['sizeMax'];
    } else {
      $request['sizeMax'] = 3.75;
    }

    if (isset($postData['report_address'])) {
      $request['address'] = $postData['report_address'];
    } else {
      $request['address'] = 'N/A';
    }
    if (isset($postData['ref_no'])) {
      $request['claim_ref'] = $postData['ref_no'];
    } else {
      $request['claim_ref'] = 'N/A';
    }
    if (isset($_GET['security_token'])) {
      $request['security_token'] = $_POST['security_token'];
    } else {
      $request['security_token'] = '22097071';
    }

    if (isset($_GET['mostRecent'])) {
      $request['mostRecent'] = $_GET['mostRecent'] === "1";
    } else {
      $request['mostRecent'] = FALSE;
    }

    // if (isset($_GET['speedMin'])) {
    //   $request['speedMin'] = $_GET['speedMin'];
    // } else {
      $request['speedMin'] = 45;
    // }
    // if (isset($_GET['speedMax'])) {
    //   $request['speedMax'] = $_GET['speedMax'];
    // } else {
      $request['speedMax'] = 200;
    // }

    //geocode if la or lng not supplied
    if (!isset($request['lat']) || !isset($request['lng']) || $request['lat'] == '' || $request['lng'] == '') {

        $address = $request['address'].', '.$postData['report_city'].', '.$postData['report_state'].', '.$postData['report_zip'];
        $apiKey = "AIzaSyBFZ8rzalnHlyYCf7_8MjP0irbjdWXJvJA";
        $geocode = file_get_contents("https://maps.google.com/maps/api/geocode/json?address=" . urlencode($address) . "&key=" . $apiKey);
        if (isset($geocode)) {
            $output = json_decode($geocode);
            if (isset($output->results[0])) {
              $request['lat'] = $output->results[0]->geometry->location->lat;
              $request['lng'] = $output->results[0]->geometry->location->lng;
            }
        }
    }

    $reportData = array();
    $reportData['reportLossDate'] = $postData['event_date']; // didnt found how to get and is this correct
    $reportData['reportSearchPeriod'] = $postData['search_date_start'].' - '. $postData['search_date_end'];
    $reportData['reportAddress'] = htmlspecialchars($postData['report_address']);
    $reportData['reportCity'] = $postData['report_city'];
    $reportData['reportState'] = $postData['report_state'];
    $reportData['reportZip'] = $postData['report_zip'];
    $reportData['layer'] = $_POST['layer_type'];
    $reportData['reportLat'] = $request['lat'];
    $reportData['reportLon'] =$request['lng'];
    $reportData['reportCounty'] = getCounty($postData['report_zip']);
    $reportData['windSpeed'] = '40-50 mph';// didnt found how to get. I think this data not need for this report
    $reportData['windDirection'] = '';// didnt found how to get I think this data not need for this report
    $reportData['hailSize'] = '0.25-0.5"';// didnt found how to get
    $reportData['images'] = array();
    $reportData['reportRef'] = htmlspecialchars($postData['ref_no']);
    $reportData['reportInsuredName'] = htmlspecialchars(trim($_POST['insured_first_name'].' '.$_POST['insured_last_name']));
    //Replace " with DOUBLE_QUOTE for command line
    $reportData['hailSize'] = implode("DOUBLE_QUOTE", explode('"', $reportData['hailSize']));

    $hailDataForImages = prepareResponse($request);
    $windDataForImages = prepareResponseWind($request);
	
    //Create a snapshot id
    $snapshotId = rand(1, 1000);

    $imageID = 0;

    // Prepare images data
    $reportAddress = $reportData['reportAddress'].', '.$reportData['reportCity'].', '.$reportData['reportState'].', '.$reportData['reportZip'];
	$urlEncodedReportAddress = rawurlencode($reportAddress);
    foreach($hailDataForImages['data'] as $key => $value) {
        if(count($reportData['images']) < 25){
            $imageID = $key;
            $imagesData = array();
            $imagesData['imageNumber'] = ''.($imageID+1);
            $imagesData['hailSize'] = ($value['max_size_5m'] ? $value['max_size_5m'] : $value['max_size_pt']); //$reportData['hailSize'];
            $imagesData['dateVal'] = $value['date'];
            $imagesDataWind['type'] = 'hail';

            //Generate snasphot
            $reportData['images'][$imageID] = array("text" => getImageText($imagesData), "data" => $imagesData, "snapshot" => null, "key" => $imageID);
            $imageID = $imageID + 1;
        }
    }
    if (isset($_SESSION['time_zone'])) {
        $timezone = $_SESSION['time_zone'];
    } else {
        $timezone = "US/Central";
    }
    global $conn4;


    foreach($windDataForImages['data'] as $key => $value) {
        if(count($reportData['images']) < 75){
            $originalDate = $value['date'];
            $newDate = date("m/d/Y", strtotime($originalDate));
            // get wind all data
            $query = "
                SELECT md.*
                FROM
                    data.metar_data md,
                    (SELECT ST_GeomFromText('POINT(".$reportData['reportLon']." ".$reportData['reportLat'].")', 4326) as geom) g
                    where md.observation_time>=date_trunc('day',(to_timestamp('" . $newDate . " 00:00:00','MM/DD/YYYY HH24:MI:SS')+ interval '1 day' )  at time zone '" . $timezone . "')  at time zone '" . $timezone . "'
                    AND md.observation_time<date_trunc('day',(to_timestamp('" . $newDate . " 00:00:00','MM/DD/YYYY HH24:MI:SS') + interval '2 days' )  at time zone '" . $timezone . "') at time zone '" . $timezone . "'
                    AND md.station_id=(
                        SELECT md.station_id
                        FROM
                        data.metar_data md,
                        (SELECT ST_GeomFromText('POINT(".$reportData['reportLon']." ".$reportData['reportLat'].")', 4326) as geom) g
                        WHERE md.observation_time>=date_trunc('day',(to_timestamp('".$newDate." 00:00:00','MM/DD/YYYY HH24:MI:SS') ))
                        AND observation_time<date_trunc('day',(to_timestamp('".$newDate." 00:00:00','MM/DD/YYYY HH24:MI:SS') + interval '1 day' ))
                        ORDER BY ST_Distance(md.geom, g.geom) ASC LIMIT 1
                    )
                ORDER BY ST_Distance(md.geom, g.geom) ASC
            ";

            $resultWind = pg_query($conn4, $query) or logError("getWindReportData", $query, pg_last_error());

            $windDirDegrees = 0;
            $windGust = 0;
            if ($resultWind !== FALSE) {
                while ($row = pg_fetch_array($resultWind, null, PGSQL_ASSOC)) {
                    if(intval($row['wind_gust_kt']) >= $windGust) {
                        $windGust = intval($row['wind_gust_kt']);
                        $windDirDegrees = intval($row['wind_dir_degrees']);
                    }
                }

                if($windGust == 0) {
                    while ($row = pg_fetch_array($resultWind, null, PGSQL_ASSOC)) {
                        if(intval($row['wind_speed_kt']) >= $windGust) {
                            $windGust = intval($row['wind_speed_kt']);
                            $windDirDegrees = intval($row['wind_dir_degrees']);
                        }
                    }
                }
            }
            $dates = $value['date'];
            $imagesDataWind = array();
            $imagesDataWind['imageNumber'] = ''.($imageID+1);
            $imagesDataWind['maxWindSpeed'] = $value['max_speed_pt'];
            $imagesDataWind['windDirDegrees'] = $windDirDegrees;
            $imagesDataWind['dateVal'] = $value['date'];
            $imagesDataWind['type'] = 'wind';

            //Generate snasphot
            $reportData['images'][$imageID] = array("text" => getImageTextWind($imagesDataWind), "data" => $imagesDataWind, "snapshot" => null, "key" => $imageID);

            $imageID = $imageID + 1;
        }
    }

    $images = $reportData['images'];

    function sortFunction( $a, $b ) {
        return strtotime($b["data"]['dateVal']) - strtotime($a["data"]['dateVal']);
    }
    usort($images, "sortFunction");

    $kk = 0;
    foreach ($images as $k => $value) {
        if($images[$k]['data']['type'] === 'wind') {
            if($_POST['windImageDisable'] == 0) {
                $images[$k]['data']['imageNumber'] = $k+1;
                $windSnapshot = file_get_contents('https://www.weatherguidance.com/payment-dev/generateHailReport/createSnapshot.php?layer=wind&lat='.$reportData['reportLat'].'&lng='.$reportData['reportLon'].'&address='.$urlEncodedReportAddress.'&hailSize='.$images[$k]['data']['maxWindSpeed'].'&dateVal='.$images[$k]['data']['dateVal']."&snapshotName=snaspshot_".$snapshotId."_".($k+1)."&picNumber=".($k+1));
            } else {
                $windSnapshot = '';
            }
            $images[$k]['text'] = getImageTextWind($images[$k]['data']);
            $images[$k]['snapshot'] = $windSnapshot;
            $images[$k]['key'] = $k;
            $images[$k]['type'] = 'wind';

        } else {
            $num = $k;
            if($_POST['windImageDisable'] == 1) {
                $num = $kk;
            }

            $images[$k]['data']['imageNumber'] = $num+1;
            $images[$k]['text'] = getImageText($images[$k]['data']);
            $hailSnapshot = file_get_contents('https://www.weatherguidance.com/payment-dev/generateHailReport/createSnapshot.php?layer=hail&lat='.$reportData['reportLat'].'&lng='.$reportData['reportLon'].'&address='.$urlEncodedReportAddress.'&hailSize='.$images[$k]['data']['hailSize'].'&dateVal='.$images[$k]['data']['dateVal']."&snapshotName=snaspshot_".$snapshotId."_".($num+1)."&picNumber=".($num+1));
            $images[$k]['snapshot'] = $hailSnapshot;
            $images[$k]['key'] = $k;
            $images[$k]['type'] = 'hail';
            if($_POST['windImageDisable'] == 1) {
                $kk = $kk + 1;
            }
        }
    }

    $reportData['images'] = $images;
    $orderData = generateReport($reportData);

	$reportLink = '/payment-dev/report_storage/'.$orderData;
	$link = '<!--- Both --><br/> <a href="/payment-dev/report_storage/'.$orderData.'">Word Report '.$orderData.'</a>';

    updateWorkOrdersStatus($ftsdb, 'done', $_GET['id'], $reportLink);
    echo $link;

} catch (Exception $e) {

    updateWorkOrdersStatus($ftsdb, 'done', $_GET['id'], '');
    print_r('ERROR appeared '.$e);
}



// FUNCTIONS
// HAIL

function getCounty($zip) {
  global $conn2;
  $sql = "SELECT \"County\" as county FROM public.\"ZIPCodes\" where \"ZipCode\"='" . $zip . "' limit 1";
  $result = pg_query($conn2, $sql) or logError("getCounty", $sql, pg_last_error());
  $data = '';
  if ($result !== FALSE) {
    $row = pg_fetch_array($result, null, PGSQL_ASSOC);
    if ($row != FALSE) {
        $data = $row['county'];
    }
    pg_free_result($result);
  }
  return $data;
}

function hailImagesOnly($val) {
    return $val['type'] == 'hail';
}

function generateReport($data) {
  global $tempDir;
  if(isset($_GET['type']) && $_GET['type'] == 'workOrders') {
      $tempDir = dirname(__FILE__) . "/report_storage/";
  }

  //global $hailSnapshot;
  require_once '/var/www/vhosts/weatherguidance.com/httpdocs/hailintel/reports/php/vendor/phpoffice/phpword/bootstrap.php';

    $templateName = 'generateHailReport/templates/verification_template.docx';

	if($data['images'] && count($data['images']) > 0){
		$num = 0;
        if($_POST['windImageDisable'] == 1) {
            $hImages = array_filter($data['images'], 'hailImagesOnly');
            $num = count($hImages);
        } else {
            $num = count($data['images']);
        }

        if ($num == 0) {
            $templateName = 'generateHailReport/templates/verification_template_'.$num.'.docx';
        } else {
            $templateName = 'generateHailReport/templates/verification_template_image_'.$num.'.docx';
        }
	}

  $templateProcessor = new \PhpOffice\PhpWord\TemplateProcessor($templateName);
  $outputFileName = $tempDir . time() . ".docx";
  if (file_exists($outputFileName)) {
      unlink($outputFileName);
  }
  $response = array();
  $templateProcessor->setValue('reportRef', $data['reportRef']);
  $templateProcessor->setValue('typeOfReport', 'hail and winds >= 50 mph ');
  $templateProcessor->setValue('reportInsuredName', !empty($data['reportInsuredName']) ? $data['reportInsuredName'] : 'N/A');
  $templateProcessor->setValue('reportLossDate', !empty($data['reportLossDate']) ? $data['reportLossDate'] : 'N/A');
  $templateProcessor->setValue('reportSearchPeriod', $data['reportSearchPeriod']);
  $templateProcessor->setValue('reportAddress', $data['reportAddress'].', '.$data['reportCity'].', '.$data['reportState'].', '.$data['reportZip']);
  $templateProcessor->setValue('reportLat', $data['reportLat']);
  $templateProcessor->setValue('reportLon', $data['reportLon']);
  $templateProcessor->setValue('reportCounty', $data['reportCounty']);
  $templateProcessor->setValue('day', date('j'));
  $templateProcessor->setValue('suff', date('S'));
  $templateProcessor->setValue('month', date('F'));
  $templateProcessor->setValue('year', date('Y'));
  
  $imageCount = count($data['images']);
  if ( $imageCount > 0) {
    $hailCount = 0;
    $windCount = 0;
    foreach ($data['images'] as $value) {
        if($value['type'] == 'hail') {
            $hailCount++;
        } else {
            $windCount++;
        }
    }
    if($hailCount === 0 || $windCount === 0) {
        $imageCount++;
    }
    $templateProcessor->cloneRow('rowValue', $imageCount);
    $rownum = 1;
    ksort($data['images']);
    $windData = 0;

    if($hailCount === 0) {
        $templateProcessor->setValue('rowValue#' . $rownum++, 'We were unable to identify any verifiable instances of hail at the insured location during the requested search period.');
    }
    if($windCount === 0) {
        $templateProcessor->setValue('rowValue#' . $rownum++, 'We were unable to identify any verifiable instances of high winds at the insured location during the requested search period.');
    }
    foreach ($data['images'] as $value) {
      $templateProcessor->setValue('rowValue#' . $rownum++, $value['text']);
      if($value['type'] == 'wind') {
          $windData++;
      }
        //Replace image
        $templateImage = 'word/media/image'.($value['key']+3).'.png';
        $templateProcessor->zipClass->deleteName($templateImage);
        $templateProcessor->zipClass->addFile($value['snapshot'], $templateImage);

      if($_POST['windImageDisable'] == 1) {
          if($value['type'] == 'hail') {
              //Replace image
              $templateImage = 'word/media/image'.($value['data']['imageNumber'] + 2).'.png';
              $templateProcessor->zipClass->deleteName($templateImage);
              $templateProcessor->zipClass->addFile($value['snapshot'], $templateImage);
          }
      } else {
          //Replace image
          $templateImage = 'word/media/image'.($value['data']['imageNumber']+2).'.png';
          $templateProcessor->zipClass->deleteName($templateImage);
          $templateProcessor->zipClass->addFile($value['snapshot'], $templateImage);
      }
    }


  }

  if ($_POST['windImageDisable'] == 0) {
    $templateProcessor->setValue('otherInfo', ' ');
        $templateProcessor->setValue('otherValue', ' ');
  } else {
		$templateProcessor->setValue('otherInfo', 'Other Requested Information:');
		$templateProcessor->setValue('otherValue', 'Weather radar data indicates that x.x inches of rain fell at the insured location on the claimed day of loss.');
	}


  $templateProcessor->saveAs($outputFileName);
  chmod($outputFileName, 0777);
  $response['success'] = TRUE;
  $response['filename'] = basename($outputFileName);

  return $response['filename'];
}

function getImageText($request) {
    $message = $request['dateVal'] . " (Image " . $request['imageNumber'] . "): High resolution radar data "
            . "indicates that hail of " . getHailText($request['hailSize']) . " in diameter occurred at "
            . "the insured location on this date. The estimated duration of the hailfall was "
            . "approximately less than 1 minute.";

  return $message;
}

function getImageTextWind($request) {
    $imageNumber = '';
    if($_POST['windImageDisable'] == 0) {
        $imageNumber = " (Image " . $request['imageNumber'] . ")";
    }
  $message = $request['dateVal'] .$imageNumber.": High resolution radar data and/or ground reports "
          . "indicate that wind gusts of " . getWindText($request['maxWindSpeed']) . " mph occurred from a general ".degToCompass($request['windDirDegrees'])." direction at the insured location on this date."
          . "  The wind gusts were likely instantaneous and brief with a duration of less than 3 minutes.";

return $message;
}

function degToCompass($az){
    $cardinalDirection = "";
    $azimuth = intval(floatval($az));
    $azimuth = $azimuth % 360;
    if ($azimuth < 11.25) {
        $cardinalDirection = "Northerly";
    } elseif ($azimuth < 33.75) {
        $cardinalDirection = "North/Northeasterly";
    } elseif ($azimuth < 56.25) {
        $cardinalDirection = "North/Easterly";
    } elseif ($azimuth < 78.75) {
        $cardinalDirection = "East/Northeasterly";
    } elseif ($azimuth < 101.25) {
        $cardinalDirection = "Easterly";
    } elseif ($azimuth < 123.75) {
        $cardinalDirection = "East/Southeasterly";
    } elseif ($azimuth < 146.25) {
        $cardinalDirection = "South/East";
    } elseif ($azimuth < 168.75) {
        $cardinalDirection = "South/Southeasterly";
    } elseif ($azimuth < 191.25) {
        $cardinalDirection = "Southerly";
    } elseif ($azimuth < 213.75) {
        $cardinalDirection = "South/Southwesterly";
    } elseif ($azimuth < 236.25) {
        $cardinalDirection = "South/Westerly";
    } elseif ($azimuth < 258.75) {
        $cardinalDirection = "West/Southwesterly";
    } elseif ($azimuth < 281.25) {
        $cardinalDirection = "Westerly";
    } elseif ($azimuth < 303.75) {
        $cardinalDirection = "West/Northwesterly";
    } elseif ($azimuth < 326.25) {
        $cardinalDirection = "North/Westerly";
    } elseif ($azimuth < 348.75) {
        $cardinalDirection = "North/Northwesterly";
    } else {
        $cardinalDirection = "Northerly";
    }
    return $cardinalDirection;
}

function getWindText($windSpeed) {
    $speed = '40-50';
    if($windSpeed <= 49) {
        $speed = '40-50';
    } else if($windSpeed >= 50 && $windSpeed <= 59) {
        $speed = '50-60';
    }else if($windSpeed >= 60 && $windSpeed <= 69) {
        $speed = '60-70';
    }else if($windSpeed >= 70 && $windSpeed <= 79) {
        $speed = '70-80';
    } else if($windSpeed >= 80 && $windSpeed <= 89) {
        $speed = '80-90';
    } else if($windSpeed >= 90 && $windSpeed <= 99) {
        $speed = '90-100';
    } else if($windSpeed >= 100 && $windSpeed <= 109) {
        $speed = '100-110';
    } else if($windSpeed >= 110 && $windSpeed <= 119) {
        $speed = '110-120';
    }

    return $speed;
}

function getHailText($hailSize) {
  $text = "".($hailSize-0.25)."-".($hailSize).'"';

  return $text;
}

function prepareResponse($request) {
    global $conn2;
    include_once dirname(__FILE__) . "/" . 'generateHailReport/userCheck.php';
    $userData = getCustomerDataFromToken($request['security_token']);
    if (substr($request['dateFrom'], 4, 1) === "-") {
        $parsedDateFrom = date_parse_from_format('Y-m-d', $request['dateFrom']);
        $parsedDateTo = date_parse_from_format('Y-m-d', $request['dateTo']);
        $dateFrom = date_create_from_format('Y-m-d', $request['dateFrom']);
        $dateTo = date_create_from_format('Y-m-d', $request['dateTo']);
    } else {
        $parsedDateFrom = date_parse_from_format('m-d-y', $request['dateFrom']);
        $parsedDateTo = date_parse_from_format('m-d-y', $request['dateTo']);
        $dateFrom = date_create_from_format('m-d-y', $request['dateFrom']);
        $dateTo = date_create_from_format('m-d-y', $request['dateTo']);
    }
    $request['dateFrom'] = date_format($dateFrom, 'Y-m-d');
    if (isset($request['mostRecent']) && $request['mostRecent']) {
        $request['dateFrom'] = '2009-08-01';
    }
     if (!isset($request['atLocation'])) {
        $request['atLocation'] = FALSE;
    }
    $request['dateTo'] = date_format($dateTo, 'Y-m-d');
    $response = array();
    $userData['isGeoValid'] = isPointGeoValid($request['lat'], $request['lng'], $userData['customer_id']);
    $pointData = "ST_GeomFromText('POINT(" . $request['lng'] . " " . $request['lat'] . ")',4326)";
    $response['timezone'] = $request['timezone'] = getTimezone($pointData);
    if ($userData['isGeoValid']) {
        $userData['isInCanada'] = isPointInCanada($request['lat'], $request['lng']);
        if ($userData['isInCanada'] && $parsedDateTo["year"] < 2015) {
            $response['error'] = "Unfortunately, this request cannot be processed. We provide hail verification reports for Canada for periods starting from 01-01-2015.";
        } else {
            $res = getHailReportData($conn2, $request, $userData, $pointData);
            $response['data'] = $res['results'];
            $response['token'] = $res['token'];
        }
    } else {
        $response['error'] = "Requested search is outside your subscription time/area";
    }

    return $response;
}

function getHailReportData($connLink, $marker, $userData, $pointData) {
    $stormReportsData = getStormReportHailData($marker, $userData);
    loggg("Start getHailReportData " . json_encode($marker));
    if ($marker['atLocation']) {
        $distances = array();
    } else {
        $distances = array("max_size_1m" => 1609, "max_size_2m" => 3218, "max_size_3m" => 4828, "max_size_5m" => 8047);
    }

    $timeRestriction = isset($userData['dates']) ? " and \"date\" in (select distinct \"date\" from subscription_dates where customer_id=" . $userData['customer_id'] . ")" : "";
    $schema = $userData['isInCanada'] ? "canada_rain" : "radar_vil";
    $data = array();


    $sql = "SELECT max(max_size) as max_size,"
            . " to_char(date, 'mm/dd/yy') as date_val, "
            . " date as date_db "
//            . "st_area(st_union(st_intersection(geom,st_transform(st_buffer(st_transform(" . $pointData . ",900913),3218,'quad_segs=4'),4326)))) as diff "
            . " FROM " . $schema . ".archive_hail_swath"
            . " WHERE "
            . " \"date\">='" . $marker['dateFrom'] . "'"
            . " AND \"date\"<='" . $marker['dateTo'] . "'"
            . " AND max_size>=" . $marker['sizeMin']
            . " AND max_size<=" . $marker['sizeMax']
            // . $timeRestriction
            . " AND St_CoveredBy(" . $pointData . ",geom)"
            . " GROUP by date,date_db"
//            . " AND (st_area(st_union(st_intersection(geom,st_transform(st_buffer(st_transform(ST_GeomFromText('POINT(" . $marker['lng'] . " " . $marker['lat'] . ")',4326),900913),3218,'quad_segs=4'),4326))))/st_area(st_transform(st_buffer(st_transform(ST_GeomFromText('POINT(" . $marker['lng'] . " " . $marker['lat'] . ")',4326),900913),3218,'quad_segs=4'),4326)))>0.51"
            . " ORDER by date desc;";

//    loggg($sql);
    $result = pg_query($connLink, $sql) or logError("getHailReportData", $sql, pg_last_error());

    if ($result !== FALSE) {
        while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
            if ($row['max_size'] !== NULL) {            //nothing to report if no match found
//                if (checkRadius2MiCoverage($conn, $marker, $row['date_db'], $pointData, $schema)) {
                $stormReportTime = 'N/A';
                if (isset($stormReportsData[$row['date_db']])) {
                    $stormReportTime = $stormReportsData[$row['date_db']]['time_val'];
                    $stormReportRecord = $stormReportsData[$row['date_db']];
                    unset($stormReportsData[$row['date_db']]);
                } else {
                    $stormReportRecord = NULL;
                }
                if ($row['max_size'] < $stormReportRecord['max_size_pt']) {
                    if ($stormReportTime === 'N/A') {
                        $stormReportRecord['max_size_pt'] = getClosestNids($pointData, $row['date_db'], $marker, $row['max_size']);
                    }
                    $record = $stormReportRecord;
                } else {

                    $record = array();
                    $record['date'] = $row['date_val'];
                    $record['max_size_pt'] = $row['max_size'];

                    $record['time_val'] = getClosestNids($pointData, $row['date_db'], $marker, $row['max_size']);
                    if ($record['time_val'] === 'N/A') {
                        if ($stormReportTime === 'N/A') {

                            $record['time_val'] = getClosestGroundReport($pointData, $row['date_db'], $marker);
                        } else {
                            $record['time_val'] = $stormReportTime;
                        }
                    }
                    foreach ($distances as $key => $value) {
                        $sqlInner = "SELECT max(max_size) as max_size from " . $schema . ".archive_hail_swath WHERE "
                                . " \"date\"='" . $row['date_db'] . "'"
                                . " AND  St_Intersects(st_transform(st_buffer(st_transform(" . $pointData . ",900913)," . $value . ",'quad_segs=4'),4326),geom);";
                        $resultInner = pg_query($connLink, $sqlInner) or logError("getHailReportData", $sqlInner, pg_last_error());
                        if ($resultInner !== FALSE) {
                            while ($rowInner = pg_fetch_array($resultInner, null, PGSQL_ASSOC)) {
                                if ($rowInner['max_size'] !== NULL) {            //nothing to report if no match found
                                    $record[$key] = $rowInner['max_size'];
                                }
                            }
                            pg_free_result($resultInner);
                        }
                    }
                }
                if ($record['time_val'] !== 'N/A') {
                    $data[$row['date_db']] = $record;
                }
//                }
            }
        }
        pg_free_result($result);
    }
    loggg("End getWindReportData");
    $data = $data + $stormReportsData;
    krsort($data);
    $results = array_values($data);
    $token = logReport($marker, $userData, $results);
    return array("results" => $results, "token" => $token);
}

function getStormReportHailData($marker, $userData) {
    global $conn2;
    loggg("Start getStormReportHailData " . json_encode($marker));
    $locationDistance = 1609;
//    $locationDistance = 8045;
    $distances = array("max_size_1m" => 16090, "max_size_2m" => 24135, "max_size_3m" => 32180, "max_size_5m" => 40225);
    $timeRestriction = isset($userData['dates']) ? " and (\"date\" - interval '12 hours')::date in (select distinct \"date\" from subscription_dates where customer_id=" . $userData['customer_id'] . ")" : "";
    $data = array();

    $pointData = "ST_GeomFromText('POINT(" . $marker['lng'] . " " . $marker['lat'] . ")',4326)";
    $sql = "SELECT max(cast_to_double(magnitude,0)) as max_size,"
            . " to_char((\"date\" - interval '12 hours'), 'mm/dd/yy') as date_val, "
            . " (\"date\"- interval '12 hours')::date as date_db "
            . " FROM storm_reports.placemarks"
            . " JOIN storm_reports.weather_types ON weather_types.id = placemarks.type_id"
            . " WHERE "
            . " weather_class='hail'"
            . " AND \"date\">=to_timestamp('" . $marker['dateFrom'] . " 12:00','YYYY-MM-DD HH24:MI') "
            . " AND \"date\"<to_timestamp('" . $marker['dateTo'] . " 12:00','YYYY-MM-DD HH24:MI') + interval '1 day'"
            . " AND cast_to_double(magnitude,0)>=" . $marker['sizeMin']
            . " AND cast_to_double(magnitude,0)<=" . $marker['sizeMax']
            . $timeRestriction
            . " AND St_dwithin(" . $pointData . "::geography,geom::geography," . $locationDistance . ")"
            . " GROUP by date_val,date_db"
            . "  ORDER by date_db desc;";
    loggg("sql****" . $sql);
    $result = pg_query($conn2, $sql) or logError("getStormReportHailData", $sql, pg_last_error());
    if ($result !== FALSE) {
        while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
            if ($row['max_size'] !== NULL) {
                $record = array();
                $record['date'] = $row['date_val'];
                $record['max_size_pt'] = number_format(floatval($row['max_size']), 2);
                $record['time_val'] = getClosestGroundReport($pointData, $row['date_db'], $marker);
                if ($record['time_val'] === 'N/A') {
                    $record['time_val'] = getClosestGroundReport($pointData, $row['date_db'], $marker);
                }
                foreach ($distances as $key => $value) {
                    $sqlInner = "SELECT max(cast_to_double(magnitude,0)) as max_size "
                            . " FROM storm_reports.placemarks"
                            . " JOIN storm_reports.weather_types ON weather_types.id = placemarks.type_id"
                            . " WHERE "
                            . " weather_class='hail'"
                            . " AND cast_to_double(magnitude,0)>=" . $marker['sizeMin']
                            . " AND cast_to_double(magnitude,0)<=" . $marker['sizeMax']
                            . " AND \"date\">=to_timestamp('" . $row['date_db'] . " 12:00','YYYY-MM-DD HH24:MI') "
                            . " AND \"date\"<to_timestamp('" . $row['date_db'] . " 12:00','YYYY-MM-DD HH24:MI') + interval '1 day'"
                            . " AND St_dwithin(" . $pointData . "::geography,geom::geography," . $value . ")";
                    loggg("##########################" . $sqlInner);
                    $resultInner = pg_query($conn2, $sqlInner) or logError("getStormReportHailDataInner", $sqlInner, pg_last_error());
                    if ($resultInner !== FALSE) {
                        while ($rowInner = pg_fetch_array($resultInner, null, PGSQL_ASSOC)) {
                            loggg("----------------------" . json_encode($row));
                            if ($rowInner['max_size'] !== NULL) {
                                $record[$key] = number_format(floatval($rowInner['max_size']), 2);
                            }
                        }
                        pg_free_result($resultInner);
                    }
                }
                $data[$row['date_db']] = $record;
            }
        }
        pg_free_result($result);
    }
    loggg("End getStormReportHailData");
    return $data;
}

function getTimezone($pointData) {
    global $conn2;
    $tz = 'US/Central';
    $sql = "SELECT tz_name FROM public.timezones"
            . " WHERE tz_name IS NOT NULL"
            . " AND st_intersects(geom," . $pointData . ")";
    $result = pg_query($conn2, $sql) or logError("getTimezone", $sql, pg_last_error());
    if ($result !== FALSE) {
        while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
            $tz = $row['tz_name'];
        }
        pg_free_result($result);
    }
    return $tz;
}

function getClosestGroundReport($pointData, $date, $marker) {
    global $conn2;
    $time = 'N/A';
    $sql = "SELECT to_char((\"date\" at time zone '" . $marker['timezone'] . "'), 'HH12:MIpm') as time_val"
            . " FROM storm_reports.placemarks"
            . " JOIN storm_reports.weather_types ON weather_types.id = placemarks.type_id"
            . " WHERE "
            . " weather_class='hail'"
            . " AND \"date\">=to_timestamp('" . $date . " 12:00','YYYY-MM-DD HH24:MI') "
            . " AND \"date\"<to_timestamp('" . $date . " 12:00','YYYY-MM-DD HH24:MI') + interval '1 day'"
            . " AND cast_to_double(magnitude,0)>=" . $marker['sizeMin']
            . " AND cast_to_double(magnitude,0)<=" . $marker['sizeMax']
            . " AND ST_DWithin(geom::geography," . $pointData . "::geography,8045)"
            . " ORDER BY geom <-> " . $pointData . " LIMIT 1";
    $result = pg_query($conn2, $sql) or logError("getClosestGroundReport", $sql, pg_last_error());
    if ($result !== FALSE) {
        while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
            $time = $row['time_val'];
        }
        pg_free_result($result);
    }
    return $time;
}

function getMeshTime($pointData, $date, $marker, $size) {
    global $conn2;
    $distance = 0.0072;  //0.6 * 0.012; 0.012-cell size
//    $distance = 8045    ;  //5*1.6090;

    $time = 'N/A';
    $sql = "SELECT  to_char((date at time zone '" . $marker['timezone'] . "'), 'HH12:MIpm') as time_val"
            . " FROM hail_mesh.cells"
            . " JOIN hail_mesh.files on files.id=cells.file_id"
            . " WHERE files.\"date\">=to_timestamp ('" . $date . " 12:00','YYYY-MM-DD HH24:MI') "
            . " AND files.\"date\"< to_timestamp ('" . $date . " 12:00','YYYY-MM-DD HH24:MI')+ interval '1 day'"
            . " AND max_size>=" . $size
//            . " AND max_size<=" . $marker['sizeMax']
            . " AND ST_DWithin(geom," . $pointData . "," . $distance . ")"
            . " ORDER BY date asc "
            . " LIMIT 1";
//            . " ORDER BY geom <-> " . $pointData . " LIMIT 1";
    $result = pg_query($conn2, $sql) or logError("getMeshTime", $sql, pg_last_error());
    if ($result !== FALSE) {
        while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
            $time = $row['time_val'];
        }
        pg_free_result($result);
    }
    return $time;
}

function getClosestNids($pointData, $date, $marker, $size = 0) {
    global $conn2;
    $parsedDate = date_parse_from_format('Y-m-d', $date);
    $startMeshDate = date_parse_from_format('Y-m-d', '2020-03-08');
    if ($parsedDate >= $startMeshDate) {
        return getMeshTime($pointData, $date, $marker, $size);
    }
    $time = 'N/A';
    $sql = "SELECT  to_char((valid at time zone '" . $marker['timezone'] . "'), 'HH12:MIpm') as time_val"
            . " FROM radar_vil.stormintel_nids"
            . " WHERE valid>=to_timestamp('" . $date . " 12:00','YYYY-MM-DD HH24:MI') "
            . " AND valid<to_timestamp('" . $date . " 12:00','YYYY-MM-DD HH24:MI') + interval '1 day'"
//            . " AND max_size>=" . $marker['sizeMin']
//            . " AND max_size<=" . $marker['sizeMax']
            . " AND ST_DWithin(geom::geography," . $pointData . "::geography,16090)"
            . " ORDER BY geom <-> " . $pointData . " LIMIT 1";
    $result = pg_query($conn2, $sql) or logError("getClosestNids", $sql, pg_last_error());
    if ($result !== FALSE) {
        while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
            $time = $row['time_val'];
        }
        pg_free_result($result);
    }
    return $time;
}

function logReportH($request, $userData) {
    global $conn2;

    $sql = "insert into reports (user_id,report_type,date_from,date_to,size_min,size_max,lat,lng,property_address,claim_reference)
	values (" . $userData['customer_id'] . ",1,$1,$2,$3,$4,$5,$6,$7,$8);";
    $params = array(
        $request['dateFrom']
        , $request['dateTo']
        , $request['sizeMin']
        , $request['sizeMax']
        , $request['lat']
        , $request['lng']
        , $request['address']
        , $request['claim_ref']
    );
    $result = pg_query_params($conn2, $sql, $params) or logError("logReport", $sql, pg_last_error());
    if ($result !== FALSE) {
        pg_free_result($result);
    }
}




function prepareResponseWind($request) {
  global $conn2, $conn3;
  // $userData = getCustomerDataFromToken($request['security_token']);
  if (substr($request['dateFrom'], 4, 1) === "-") {
      $parsedDateFrom = date_parse_from_format('Y-m-d', $request['dateFrom']);
      $parsedDateTo = date_parse_from_format('Y-m-d', $request['dateTo']);
      $dateFrom = date_create_from_format('Y-m-d', $request['dateFrom']);
      $dateTo = date_create_from_format('Y-m-d', $request['dateTo']);
  } else {
      $parsedDateFrom = date_parse_from_format('m-d-y', $request['dateFrom']);
      $parsedDateTo = date_parse_from_format('m-d-y', $request['dateTo']);
      $dateFrom = date_create_from_format('m-d-y', $request['dateFrom']);
      $dateTo = date_create_from_format('m-d-y', $request['dateTo']);
  }
  $request['dateFrom'] = date_format($dateFrom, 'Y-m-d');
  if (isset($request['mostRecent']) && $request['mostRecent']) {
      $request['dateFrom'] = '2010-01-01';
  }
  if (!isset($request['atLocation'])) {
      $request['atLocation'] = FALSE;
  }
  $request['dateTo'] = date_format($dateTo, 'Y-m-d');

  $response = array();

  $res = getWindReportData($conn2, $conn3, $request, $userData);
  $response['data'] = $res['results'];
  $response['token'] = $res['token'];

  return $response;
}

function getWindReportData($conn2, $conn3, $marker, $userData) {
    $stormReportsData = getStormReportWindData($conn2, $marker, $userData);
    loggg("Start getWindReportData");
    if ($marker['atLocation']) {
        $distances = array();
    } else {
        $distances = array("max_speed_1m" => 1609, "max_speed_2m" => 3218, "max_speed_3m" => 4828, "max_speed_5m" => 8047);
    }

    $timeRestriction = isset($userData['dates']) ? " and date_val in ('" . implode("', '", $userData['dates']) . "')" : "";
    $data = array();

    $pointData = "ST_GeomFromText('POINT(" . $marker['lng'] . " " . $marker['lat'] . ")',4326)";

    $sql = "SELECT max(max_speed) as max_speed,"
            . " to_char(date_val, 'mm/dd/yy') as date_val, "
            . " date_val as date_db "
            . " FROM wind_processing_rtma.daily_swath"
//            . " FROM wind_processing.daily_swath"
            . " WHERE "
            . " date_val>='" . $marker['dateFrom'] . "'"
            . " AND date_val<='" . $marker['dateTo'] . "'"
            . " AND max_speed>=" . $marker['speedMin']
            . " AND max_speed<=" . $marker['speedMax']
            . $timeRestriction
            . " AND St_CoveredBy(" . $pointData . ",geom)"
            . " GROUP by date_val,date_db"
            . " ORDER by date_val desc;";
    loggg("getWindReportData*****" . $sql);
    $result = pg_query($conn3, $sql) or logError("getWindReportData", $sql, pg_last_error());
    if ($result !== FALSE) {
        while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
            if ($row['max_speed'] !== NULL) {            //nothing to report if no match found
//                if (checkRadius2MiCoverage($conn2, $marker, $row['date_db'], $pointData)) {
                $record = array();
                if (isset($stormReportsData[$row['date_db']])) {
                    if ($stormReportsData[$row['date_db']]['within_mile']) {
                        unset($stormReportsData[$row['date_db']]['within_mile']);
                        $record = $stormReportsData[$row['date_db']];
                        unset($stormReportsData[$row['date_db']]);
                    } else {
                        unset($stormReportsData[$row['date_db']]);
                    }
                }
                if (empty($record)) {

                    $record['date'] = $row['date_val'];
                    $record['max_speed_pt'] = $row['max_speed'];
                    foreach ($distances as $key => $value) {
//                        $sqlInner = "SELECT max(max_speed) as max_speed from wind_processing.daily_swath "
                        $sqlInner = "SELECT max(max_speed) as max_speed from wind_processing_rtma.daily_swath "
                                . " WHERE "
                                . " date_val='" . $row['date_db'] . "'"
                                . " AND max_speed>=" . $marker['speedMin']
                                . " AND max_speed<=" . $marker['speedMax']
                                . " AND St_dwithin(" . $pointData . "::geography,geom::geography," . $value . ")";
                        loggg("getWindReportDataINNER*****" . $sqlInner);
                        $resultInner = pg_query($conn3, $sqlInner) or logError("getWindReportData", $sqlInner, pg_last_error());
                        if ($resultInner !== FALSE) {
                            while ($rowInner = pg_fetch_array($resultInner, null, PGSQL_ASSOC)) {
                                if ($rowInner['max_speed'] !== NULL) {            //nothing to report if no match found
                                    $record[$key] = $rowInner['max_speed'];
                                }
                            }
                            pg_free_result($resultInner);
                        }
                    }
                }
                $data[$row['date_db']] = $record;
            }
        }
        pg_free_result($result);
    }
    loggg("End getWindReportData");
    $data = $data + $stormReportsData;
    krsort($data);
    $results = array_values($data);
    $token = logReport($marker, $userData, $results);
    return array("results" => $results, "token" => $token);
}

function getStormReportWindData($conn, $marker, $userData) {
    loggg("Start getStormReportWindData");
    $locationCheck = 1609;
    $locationDistance = 16090;
    if ($marker['atLocation']) {
        $distances = array();
    } else {
        $distances = array("max_speed_1m" => 24135, "max_speed_2m" => 32180, "max_speed_3m" => 40225, "max_speed_5m" => 48270);
    }
    $timeRestriction = isset($userData['dates']) ? " and (\"date\" - interval '12 hours')::date in (select distinct \"date\" from subscription_dates where customer_id=" . $userData['customer_id'] . ")" : "";
    $data = array();
    $hurricaneData = getStormReportStormHurricaneData($conn, $marker, $userData);
    $pointData = "ST_GeomFromText('POINT(" . $marker['lng'] . " " . $marker['lat'] . ")',4326)::geography";
//    $radiusData = "st_transform(st_buffer(st_transform(" . $pointData . ",900913)," . $locationDistance . ",'quad_segs=8'),4326)";
    $sql = "SELECT max(cast_to_double(magnitude,0)) as max_speed,"
            . " to_char((\"date\" - interval '12 hours'), 'mm/dd/yy') as date_val, "
            . " (\"date\"- interval '12 hours')::date as date_db, "
            . " bool_or(St_dwithin(" . $pointData . ",geom::geography," . $locationCheck . ")) as  within_mile"
            . " FROM storm_reports.placemarks"
            . " JOIN storm_reports.weather_types ON weather_types.id = placemarks.type_id"
            . " WHERE "
            . " weather_class='wind'"
            . " AND \"date\">=to_timestamp('" . $marker['dateFrom'] . " 12:00','YYYY-MM-DD HH24:MI') "
            . " AND \"date\"<to_timestamp('" . $marker['dateTo'] . " 12:00','YYYY-MM-DD HH24:MI') + interval '1 day'"
            . " AND cast_to_double(magnitude,0)>=" . $marker['speedMin']
            . " AND cast_to_double(magnitude,0)<=" . $marker['speedMax']
            . " AND type_id NOT IN (44,46)"
            . $timeRestriction
//            . " AND St_intersects(" . $radiusData . ",geom)"
            . " AND St_dwithin(" . $pointData . ",geom::geography," . $locationDistance . ")"
            . " GROUP by date_val,date_db"
            . "  ORDER by date_db desc;";
    loggg("sql****" . $sql);
    $result = pg_query($conn, $sql) or logError("getStormReportWindData", $sql, pg_last_error());
    if ($result !== FALSE) {
        while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
            if ($row['max_speed'] !== NULL) {            //nothing to report if no match found
                if (isset($hurricaneData[$row['date_db']])) {
                    $data[$row['date_db']] = $hurricaneData[$row['date_db']];
                    unset($hurricaneData[$row['date_db']]);
                    continue;
                }
                $record = array();
                $record['date'] = $row['date_val'];
                $record['max_speed_pt'] = $row['max_speed'];
                $record['within_mile'] = $row['within_mile'] === 't';
                foreach ($distances as $key => $value) {
                    $sqlInner = "SELECT max(cast_to_double(magnitude,0)) as max_speed "
                            . " FROM storm_reports.placemarks"
                            . " JOIN storm_reports.weather_types ON weather_types.id = placemarks.type_id"
                            . " WHERE "
                            . " weather_class='wind'"
                            . " AND type_id NOT IN (44,46)"
                            . " AND cast_to_double(magnitude,0)>=" . $marker['speedMin']
                            . " AND cast_to_double(magnitude,0)<=" . $marker['speedMax']
                            . " AND \"date\">=to_timestamp('" . $row['date_db'] . " 12:00','YYYY-MM-DD HH24:MI') "
                            . " AND \"date\"<to_timestamp('" . $row['date_db'] . " 12:00','YYYY-MM-DD HH24:MI') + interval '1 day'"
                            . " AND St_dwithin(" . $pointData . ",geom::geography," . $value . ")";
//                            . " AND  St_Intersects(st_transform(st_buffer(st_transform(" . $pointData . ",900913)," . $value . ",'quad_segs=8'),4326),geom);";
                    loggg("##########################" . $sqlInner);
                    $resultInner = pg_query($conn, $sqlInner) or logError("getStormReportWindData", $sqlInner, pg_last_error());
                    if ($resultInner !== FALSE) {
                        while ($rowInner = pg_fetch_array($resultInner, null, PGSQL_ASSOC)) {
                            if ($rowInner['max_speed'] !== NULL) {            //nothing to report if no match found
                                $record[$key] = $rowInner['max_speed'];
                            }
                        }
                        pg_free_result($resultInner);
                    }
                }
                $data[$row['date_db']] = $record;
            }
        }
        pg_free_result($result);
    }
    $data = $data + $hurricaneData;
    loggg("End getStormReportWindData");
    return $data;
}

function getStormReportStormHurricaneData($conn, $marker, $userData) {
    loggg("Start getStormReportStormHurricaneData");
    $locationDistance = 64360;
    if ($marker['atLocation']) {
        $distances = array();
    } else {
        $distances = array("max_speed_1m" => 80450, "max_speed_2m" => 96540, "max_speed_3m" => 112630, "max_speed_5m" => 128720);
    }

    $timeRestriction = isset($userData['dates']) ? " and (\"date\" - interval '12 hours')::date in (select distinct \"date\" from subscription_dates where customer_id=" . $userData['customer_id'] . ")" : "";
    $data = array();

    $pointData = "ST_GeomFromText('POINT(" . $marker['lng'] . " " . $marker['lat'] . ")',4326)::geography";
//    $radiusData = "st_transform(st_buffer(st_transform(" . $pointData . ",900913)," . $locationDistance . ",'quad_segs=8'),4326)";
    $sql = "SELECT max(cast_to_double(magnitude,0)) as max_speed,"
            . " to_char((\"date\" - interval '12 hours'), 'mm/dd/yy') as date_val, "
            . " (\"date\"- interval '12 hours')::date as date_db "
            . " FROM storm_reports.placemarks"
            . " JOIN storm_reports.weather_types ON weather_types.id = placemarks.type_id"
            . " WHERE "
            . " type_id IN (44,46)"
            . " AND \"date\">=to_timestamp('" . $marker['dateFrom'] . " 12:00','YYYY-MM-DD HH24:MI') "
            . " AND \"date\"<to_timestamp('" . $marker['dateTo'] . " 12:00','YYYY-MM-DD HH24:MI') + interval '1 day'"
            . " AND cast_to_double(magnitude,0)>=" . $marker['speedMin']
            . " AND cast_to_double(magnitude,0)<=" . $marker['speedMax']
            . $timeRestriction
//            . " AND St_intersects(" . $radiusData . ",geom)"
            . " AND St_dwithin(" . $pointData . ",geom::geography," . $locationDistance . ")"
            . " GROUP by date_val,date_db"
            . "  ORDER by date_db desc;";
    loggg("sql****" . $sql);
    $result = pg_query($conn, $sql) or logError("getStormReportWindData", $sql, pg_last_error());
    if ($result !== FALSE) {
        while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
            if ($row['max_speed'] !== NULL) {            //nothing to report if no match found
                $record = array();
                $record['date'] = $row['date_val'];
                $record['max_speed_pt'] = $row['max_speed'];
                $record['within_mile'] = FALSE;
                foreach ($distances as $key => $value) {
                    $sqlInner = "SELECT max(cast_to_double(magnitude,0)) as max_speed "
                            . " FROM storm_reports.placemarks"
                            . " JOIN storm_reports.weather_types ON weather_types.id = placemarks.type_id"
                            . " WHERE "
                            . " type_id IN (44,46)"
                            . " AND cast_to_double(magnitude,0)>=" . $marker['speedMin']
                            . " AND cast_to_double(magnitude,0)<=" . $marker['speedMax']
                            . " AND \"date\">=to_timestamp('" . $row['date_db'] . " 12:00','YYYY-MM-DD HH24:MI') "
                            . " AND \"date\"<to_timestamp('" . $row['date_db'] . " 12:00','YYYY-MM-DD HH24:MI') + interval '1 day'"
                            . " AND St_dwithin(" . $pointData . ",geom::geography," . $value . ")";
//                            . " AND  St_Intersects(st_transform(st_buffer(st_transform(" . $pointData . ",900913)," . $value . ",'quad_segs=8'),4326),geom);";
                    loggg("##########################" . $sqlInner);
                    $resultInner = pg_query($conn, $sqlInner) or logError("getStormReportWindData", $sqlInner, pg_last_error());
                    if ($resultInner !== FALSE) {
                        while ($rowInner = pg_fetch_array($resultInner, null, PGSQL_ASSOC)) {
                            if ($rowInner['max_speed'] !== NULL) {            //nothing to report if no match found
                                $record[$key] = $rowInner['max_speed'];
                            }
                        }
                        pg_free_result($resultInner);
                    }
                }
                $data[$row['date_db']] = $record;
            }
        }
        pg_free_result($result);
    }
    loggg("End getStormReportStormHurricaneData");
    return $data;
}

function checkRadius2MiCoverage($conn, $marker, $date, $pointData) {
    $sql = "SELECT (st_area(st_union(st_intersection(geom,st_transform(st_buffer(st_transform(" . $pointData . ",900913),3218,'quad_segs=4'),4326))))"
            . "/st_area(st_transform(st_buffer(st_transform(" . $pointData . ",900913),3218,'quad_segs=4'),4326)))>0.51 as coverage "
//            . "/0.0022436252082447 )>0.51 as coverage "
            . " FROM storm_reports.placemarks"
            . " WHERE "
            . " \"date\"='" . $date . "'"
            . " AND max_size>=" . $marker['sizeMin']
            . " AND max_size<=" . $marker['sizeMax'];
    $result = pg_query($conn, $sql) or logError("checkRadius2MiCoverage", $sql, pg_last_error());
    if ($result !== FALSE) {
        while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
            if ($row['coverage'] === 't') {
                pg_free_result($result);
                return TRUE;
            }
        }
    }
    return FALSE;
}

function logReport($request, $userData, $results = "") {
    global $conn2;
    $token = strtolower(substr(uniqid(), -6));
    while (hasToken($token)) {
        $token = strtolower(substr(uniqid(), -6));
    }
//    $conn2 = pg_connect("host=127.0.0.1 dbname=sitewarn user=viamap password=101273Benz$ port=5432");
//    $conn2 = pg_connect("host10.0.0.64 dbname=sitewarn user=viamap password=101273Benz$ port=5432");
    $sql = "insert into reports (user_id,report_type,date_from,date_to,size_min,size_max,lat,lng,
        property_address,claim_reference,token,results,at_location)
	values (" . $userData['customer_id'] . ",2,$1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11);";
    $params = array(
        $request['dateFrom']
        , $request['dateTo']
        , $request['speedMin']
        , $request['speedMax']
        , $request['lat']
        , $request['lng']
        , $request['address']
        , $request['claim_ref']
        , $token
        , json_encode($results)
        , $request['atLocation'] ? 't' : 'f'
    );
    $result = pg_query_params($conn2, $sql, $params) or logError("logReport", $sql, pg_last_error());
    if ($result !== FALSE) {
        pg_free_result($result);
    }
    return $token;
}

function logReportW($request, $userData, $results = "") {
  global $conn2;
  $token = strtolower(substr(uniqid(), -6));
  while (hasToken($token)) {
      $token = strtolower(substr(uniqid(), -6));
  }
  $sql = "insert into reports (user_id,report_type,date_from,date_to,size_min,size_max,lat,lng,
      property_address,claim_reference,token,results,at_location)
values (" . $userData['customer_id'] . ",2,$1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11);";
  $params = array(
      $request['dateFrom']
      , $request['dateTo']
      , $request['speedMin']
      , $request['speedMax']
      , $request['lat']
      , $request['lng']
      , $request['address']
      , $request['claim_ref']
      , $token
      , json_encode($results)
      , $request['atLocation'] ? 't' : 'f'
  );
  $result = pg_query_params($conn2, $sql, $params) or logError("logReport", $sql, pg_last_error());
  if ($result !== FALSE) {
      pg_free_result($result);
  }
  return $token;
}

function hasToken($token) {
  global $conn2;
  $sql = "select id from reports WHERE token=$1;";
  $params = array(
      $token
  );
  $result = pg_query_params($conn2, $sql, $params) or logError("hasToken", $sql, pg_last_error());
  if ($result !== FALSE) {
      while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
          return TRUE;
      }
      pg_free_result($result);
  }
  return FALSE;
}

function loggg($function) {
  $time = date("Y-m-d_H:i:s");
  $filename = "dbg.log";
  file_put_contents($filename, $time . "\t" . $function . "\n", FILE_APPEND);
}
