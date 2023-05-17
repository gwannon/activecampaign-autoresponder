<?php

include_once("config.php");

echo date("Y-m-d H:i:s")."----------------------\n";

$inbox = imap_open($mailbox, $username, $password) or die('Cannot connect to email: ' . imap_last_error());

$emails_ids = imap_search($inbox, 'ALL');
print_r($emails_ids);
if($emails_ids) {
  rsort($emails_ids);
  //Recorremos los emails
  foreach($emails_ids as $msg_number) {
    $header = imap_headerinfo($inbox, $msg_number);
    foreach ($header->sender as $sender) {
      $contacts[] = [
        "name" => mb_decode_mimeheader($sender->personal),
        "email" => strtolower($sender->mailbox."@".$sender->host),
      ];
    }
    //Borramos el mensaje
    imap_delete($inbox, $msg_number);
  }

  //Borramos de la papelera los correos.
  imap_expunge($inbox);

  //Procesamos los emails
  foreach ($contacts as $contact) {
    if(existsUserAC ($contact['email'])) {
      echo $contact['name']." <".$contact['email']."> existe\n";
      $temp = curlCallGet("/contacts?email=".$contact['email']);
      $response = $temp->contacts[0];
    } else {
      echo $contact['name']." <".$contact['email']."> NO existe\n";
      $data['contact'] = [
        'email' => $contact['email'], 
        'firstName' => $contact['name'] 
      ];
      $response = curlCallPost("/contacts", json_encode($data))->contact;
      
    }

    //Ejecutamos la automatización del autorespondedor
    $data['contactAutomation'] = [
      "contact" => $response->id,
      "automation" => AC_API_AUTOM
    ];
    $response = curlCallPost("/contactAutomations", json_encode($data));
  }
}

echo "----------------------\n";












//Funciones AC------------------------------
function existsUserAC ($email) {
  $temp = curlCallGet("/contacts?email=".$email);
  if(isset($temp->contacts[0])) return true;
	else return false;
}

//Funciones CURL-----------------------------
function curlCall($link, $request = 'GET', $payload = false) {
  $now = date("Y-m-d H:i:s");
  $curl = curl_init();
  curl_setopt($curl, CURLOPT_URL, AC_API_URL.$link);
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($curl, CURLOPT_HTTPHEADER, array('Api-Token: '.AC_API_KEY));
  curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 1);
  if (in_array($request, array("PUT", "POST", "DELETE"))) curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $request);
  if ($payload) curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
  $response = curl_exec($curl);
  $json = json_decode($response);
  $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
  curl_close($curl);
  if (in_array($httpcode, array(200, 201))) {
    curlLog("log", $now, $link, $request, $payload );
    return $json;
  } else {
    curlLog("errors", $now, $link, $request, $payload, $httpcode, json_encode($json));
    return false;
  }
}

//GET
function curlCallGet($link) { return curlCall($link); }

//PUT
function curlCallPut($link, $payload) { return curlCall($link, "PUT", $payload); }

//POST
function curlCallPost($link, $payload) { return curlCall($link, "POST", $payload); }

//DELETE
function curlCallDelete($link) { return curlCall($link, "DELETE"); }

//Log system
function curlLog($file, $now, $link, $request, $payload, $httpcode = "", $json = "") {
  $f = fopen(dirname(__FILE__)."/logs/".$file.".txt", "a+");
  $line = date("Y-m-d H:i:s")."|".$now."|".$link."|".$request."|".$payload."|".$httpcode."|".$json."\n";
  fwrite($f, $line);
  fclose($f);
}