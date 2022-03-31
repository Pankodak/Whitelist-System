<?php

session_start();
require_once 'cfg.php';

$data = array();
if(isset($_POST['type'])){
    // if($_POST['type'] == 'refreshserver'){
    //     $content = @file_get_contents("http://".$c['serverip']."/players.json");
    //     $content = json_decode($content, true);
    //     if($content != false){
    //         $data['type'] = 'success';
    //         $data['message'] = 'Serwer ON: '.count($content).'/50';
    //         echo json_encode($data);
    //         exit();
    //     } else{
    //         $data['type'] = 'success';
    //         $data['message'] = 'Serwer OFF';
    //         echo json_encode($data);
    //         exit();
    //     }
    // }
    if(!isset($_SESSION['islogged'])){
        $data['type'] = 'error';
        $data['message'] = 'Nie jesteś zalogowany/a!';
        echo json_encode($data);
        exit();
    }
    if($_POST['type'] == 'sendapplication'){
        $sql = @new mysqli($c['mysql']['ip'], $c['mysql']['user'], $c['mysql']['password'], $c['mysql']['database']);
        if($sql->connect_errno){
            $sql->close();
            $data['type'] = 'error';
            $data['message'] = 'Wystąpił błąd z połączeniem się do bazy danych!';
            echo json_encode($data);
            exit();
        }
        $questions = array();
        $exist = true;
        foreach($_POST['questions'] as $key => $value){
            $question = str_replace('q', '', $value['name']);
            if(!isset($c['questions'][$question])){
                $sql->close();
                $data['type'] = 'error';
                $data['message'] = 'Uzupełnij wszystkie pola!'.$question;
                echo json_encode($data);
                exit();
            }
            if($c['questions'][$question]['type'] == 'radio' && $c['questions'][$question]['correct'] == true){
                if(!in_array($value['value'], $c['questions'][$question]['correct-radio'])){
                    $data['type'] = 'error';
                    $data['message'] = $c['questions'][$question]['msg'];
                    echo json_encode($data);
                    exit();
                }
            }
            $questions[] = array('title' => $c['questions'][$question]['title'], 'answer' => mysqli_real_escape_string($sql, $value['value']));
        }
        $query = "INSERT INTO `applications` (`discord_id`, `discord_username`, `discord_discriminator`, `discord_email`, `questions`, `date-created`, `discord_status`, `status`) VALUES ('".$_SESSION['id']."', '".$_SESSION['username']."', '".$_SESSION['discriminator']."', '".$_SESSION['email']."', '".json_encode($questions, JSON_UNESCAPED_UNICODE)."', CURRENT_TIMESTAMP, 'waiting', 'waiting');";
        $sql->query($query);
        $sql->close();
        //$data['questions'] = json_encode($_POST['questions']);
        $data['type'] = 'success';
        $data['message'] = 'Poprawnie wysłano aplikacje.';
        echo json_encode($data);
        exit();
    }
    if($_POST['type'] == 'admin-waiting'){
        if(!isset($_SESSION['group']) || !isset($c['admin']['groups'][$_SESSION['group']])){
            $data['type'] = 'error';
            $data['message'] = 'Nie masz uprawnień';
            echo json_encode($data);
            exit();
        }
        if(in_array('accept_app', $c['admin']['groups'][$_SESSION['group']]) || in_array('discard_app', $c['admin']['groups'][$_SESSION['group']])){

        } else{
            $data['type'] = 'error';
            $data['message'] = 'Nie masz uprawnień';
            echo json_encode($data);
            exit();
        }
        $sql = @new mysqli($c['mysql']['ip'], $c['mysql']['user'], $c['mysql']['password'], $c['mysql']['database']);

        if($sql->connect_errno){
            $data['type'] = 'error';
            $data['message'] = 'Wystąpił błąd z połączeniem się do bazy!';
            $sql->close();
            echo json_encode($data);
            exit();
        }

        $zap = $sql->query('SELECT * FROM `admins` WHERE `discord_id`="'.$_SESSION['id'].'";');
        if($zap->num_rows == 0){
            $sql->close();
            $data['type'] = 'error';
            $data['message'] = 'Wystąpił nieznany błąd!';
            echo json_encode($data);
            exit();
        }

        $result = @$sql->query('SELECT * FROM `applications` WHERE `status`="waiting" AND `discord_status` is NULL ORDER BY `id`;');

        $data['list'] = array();
        while($row = $result->fetch_assoc()){
            $data['list'][] = array($row['id'], $row['discord_username'].'#'.$row['discord_discriminator'], $row['date-created'], '<button type="button" class="show-button waves-effect waves-light btn modal-trigger" onclick="openwaiting('.$row['id'].');">Zobacz</button>');
        }

        $sql->close();
        $data['type'] = 'success';
        $data['message'] = 'Odświeżono listę oczekujących!';
        echo json_encode($data);
        exit();
    }
    if($_POST['type'] == 'admin-recruitmentstage'){
        if(!isset($_SESSION['group']) || !isset($c['admin']['groups'][$_SESSION['group']])){
            $data['type'] = 'error';
            $data['message'] = 'Nie masz uprawnień';
            echo json_encode($data);
            exit();
        }

        if(in_array('have_conversation', $c['admin']['groups'][$_SESSION['group']])){

        } else{
            $data['type'] = 'error';
            $data['message'] = 'Nie masz uprawnień';
            echo json_encode($data);
            exit();
        }

        $sql = @new mysqli($c['mysql']['ip'], $c['mysql']['user'], $c['mysql']['password'], $c['mysql']['database']);

        if($sql->connect_errno){
            $data['type'] = 'error';
            $data['message'] = 'Wystąpił błąd z połączeniem się do bazy!';
            $sql->close();
            echo json_encode($data);
            exit();
        }

        $zap = $sql->query('SELECT * FROM `admins` WHERE `discord_id`="'.$_SESSION['id'].'";');
        if($zap->num_rows == 0){
            $sql->close();
            $data['type'] = 'error';
            $data['message'] = 'Wystąpił nieznany błąd!';
            echo json_encode($data);
            exit();
        }

        $result = @$sql->query('SELECT * FROM `applications` WHERE `status`="conversation" ORDER BY id;');

        $data['list'] = array();
        while($row = $result->fetch_assoc()){
            $data['list'][] = array($row['id'], $row['discord_username'].'#'.$row['discord_discriminator'], $row['date-created'], $row['date-checked'], $row['checked'], '<button type="button" class="show-button waves-effect waves-light btn modal-trigger" onclick="showrecruitmentstage('.$row['id'].');">Zobacz</button>');
        }

        $sql->close();
        $data['type'] = 'success';
        $data['message'] = 'Odświeżono listę rozmów!';
        echo json_encode($data);
        exit();
    }
    if($_POST['type'] == 'admin-accepted'){
        if(!isset($_SESSION['group']) || !isset($c['admin']['groups'][$_SESSION['group']])){
            $data['type'] = 'error';
            $data['message'] = 'Nie masz uprawnień';
            echo json_encode($data);
            exit();
        }

        if(in_array('accept_app', $c['admin']['groups'][$_SESSION['group']])){

        } else{
            $data['type'] = 'error';
            $data['message'] = 'Nie masz uprawnień';
            echo json_encode($data);
            exit();
        }

        $sql = @new mysqli($c['mysql']['ip'], $c['mysql']['user'], $c['mysql']['password'], $c['mysql']['database']);

        if($sql->connect_errno){
            $data['type'] = 'error';
            $data['message'] = 'Wystąpił błąd z połączeniem się do bazy!';
            $sql->close();
            echo json_encode($data);
            exit();
        }

        $zap = $sql->query('SELECT * FROM `admins` WHERE `discord_id`="'.$_SESSION['id'].'";');
        if($zap->num_rows == 0){
            $sql->close();
            $data['type'] = 'error';
            $data['message'] = 'Wystąpił nieznany błąd!';
            echo json_encode($data);
            exit();
        }

        $result = @$sql->query('SELECT * FROM `applications` WHERE `status`="accepted" ORDER BY id;');

        $data['list'] = array();
        while($row = $result->fetch_assoc()){
            $data['list'][] = array($row['id'], $row['discord_username'].'#'.$row['discord_discriminator'], $row['date-created'], $row['date-checked'], $row['checked'], '<button type="button" class="show-button waves-effect waves-light btn modal-trigger" onclick="showapplication('.$row['id'].');">Zobacz</button>');
        }

        $sql->close();
        $data['type'] = 'success';
        $data['message'] = 'Odświeżono listę zaakceptowanych!';
        echo json_encode($data);
        exit();
    }
    if($_POST['type'] == 'admin-rejected'){
        if(!isset($_SESSION['group']) || !isset($c['admin']['groups'][$_SESSION['group']])){
            $data['type'] = 'error';
            $data['message'] = 'Nie masz uprawnień';
            echo json_encode($data);
            exit();
        }

        if(in_array('discard_app', $c['admin']['groups'][$_SESSION['group']])){

        } else{
            $data['type'] = 'error';
            $data['message'] = 'Nie masz uprawnień';
            echo json_encode($data);
            exit();
        }

        $sql = @new mysqli($c['mysql']['ip'], $c['mysql']['user'], $c['mysql']['password'], $c['mysql']['database']);

        if($sql->connect_errno){
            $data['type'] = 'error';
            $data['message'] = 'Wystąpił błąd z połączeniem się do bazy!';
            $sql->close();
            echo json_encode($data);
            exit();
        }

        $zap = $sql->query('SELECT * FROM `admins` WHERE `discord_id`="'.$_SESSION['id'].'";');
        if($zap->num_rows == 0){
            $sql->close();
            $data['type'] = 'error';
            $data['message'] = 'Wystąpił nieznany błąd!';
            echo json_encode($data);
            exit();
        }

        $result = @$sql->query('SELECT * FROM `applications` WHERE `status`="rejected" ORDER BY id;');

        $data['list'] = array();
        while($row = $result->fetch_assoc()){
            $data['list'][] = array($row['id'], $row['discord_username'].'#'.$row['discord_discriminator'], $row['date-created'], $row['date-checked'], $row['checked'], '<button type="button" class="show-button waves-effect waves-light btn modal-trigger" onclick="showapplication('.$row['id'].');">Zobacz</button>');
        }

        $sql->close();
        $data['type'] = 'success';
        $data['message'] = 'Odświeżono listę odrzuconych!';
        echo json_encode($data);
        exit();
    }

    if($_POST['type'] == 'admin-admins'){
        if(!isset($_SESSION['group']) || !isset($c['admin']['groups'][$_SESSION['group']])){
            $data['type'] = 'error';
            $data['message'] = 'Nie masz uprawnień';
            echo json_encode($data);
            exit();
        }

        if(in_array('add_admins', $c['admin']['groups'][$_SESSION['group']]) || in_array('remove_admins', $c['admin']['groups'][$_SESSION['group']])){

        } else{
            $data['type'] = 'error';
            $data['message'] = 'Nie masz uprawnień';
            echo json_encode($data);
            exit();
        }

        $sql = @new mysqli($c['mysql']['ip'], $c['mysql']['user'], $c['mysql']['password'], $c['mysql']['database']);

        if($sql->connect_errno){
            $data['type'] = 'error';
            $data['message'] = 'Wystąpił błąd z połączeniem się do bazy!';
            $sql->close();
            echo json_encode($data);
            exit();
        }

        $zap = $sql->query('SELECT * FROM `admins` WHERE `discord_id`="'.$_SESSION['id'].'";');
        if($zap->num_rows == 0){
            $sql->close();
            $data['type'] = 'error';
            $data['message'] = 'Wystąpił nieznany błąd!';
            echo json_encode($data);
            exit();
        }

        $result = @$sql->query('SELECT * FROM `admins`;');

        $data['list'] = array();
        while($row = $result->fetch_assoc()){
            $data['list'][] = array($row['discord_name'], $row['discord_id'], $row['group'], '<button type="button" id="deleteadmin" class="modal-close waves-effect waves-light btn" style="background-color: red;" onclick="deleteadmin('.$row['id'].');">Usuń</button>');
        }

        $sql->close();
        $data['type'] = 'success';
        $data['message'] = 'Odświeżono listę administratorów!';
        echo json_encode($data);
        exit();
    }

    if($_POST['type'] == 'admin-blocked'){
        if(!isset($_SESSION['group']) || !isset($c['admin']['groups'][$_SESSION['group']])){
            $data['type'] = 'error';
            $data['message'] = 'Nie masz uprawnień';
            echo json_encode($data);
            exit();
        }

        if(in_array('add_to_block', $c['admin']['groups'][$_SESSION['group']]) || in_array('remove_from_block', $c['admin']['groups'][$_SESSION['group']])){

        } else{
            $data['type'] = 'error';
            $data['message'] = 'Nie masz uprawnień';
            echo json_encode($data);
            exit();
        }

        $sql = @new mysqli($c['mysql']['ip'], $c['mysql']['user'], $c['mysql']['password'], $c['mysql']['database']);

        if($sql->connect_errno){
            $data['type'] = 'error';
            $data['message'] = 'Wystąpił błąd z połączeniem się do bazy!';
            $sql->close();
            echo json_encode($data);
            exit();
        }

        $zap = $sql->query('SELECT * FROM `admins` WHERE `discord_id`="'.$_SESSION['id'].'";');
        if($zap->num_rows == 0){
            $sql->close();
            $data['type'] = 'error';
            $data['message'] = 'Wystąpił nieznany błąd!';
            echo json_encode($data);
            exit();
        }

        $result = @$sql->query('SELECT * FROM `blocked`;');

        $data['list'] = array();
        while($row = $result->fetch_assoc()){
            $data['list'][] = array($row['discord_id'], $row['discord_username'].'#'.$row['discord_discriminator'], '<button type="button" id="deleteuser" class="modal-close waves-effect waves-light btn" style="background-color: red;" onclick="deleteblocked(\''.$row['discord_id'].'\');">Usuń</button>');
        }

        $sql->close();
        $data['type'] = 'success';
        $data['message'] = 'Odświeżono listę zablokowanych!';
        echo json_encode($data);
        exit();
    }

    if($_POST['type'] == 'load-waiting-application'){
        if(!isset($_POST['id'])){
            $data['type'] = 'error';
            $data['message'] = 'Nie podałeś ID!';
            echo json_encode($data);
            exit();
        }

        if(in_array('accept_app', $c['admin']['groups'][$_SESSION['group']]) || in_array('discard_app', $c['admin']['groups'][$_SESSION['group']])){

        } else{
            $data['type'] = 'error';
            $data['message'] = 'Nie masz uprawnień';
            echo json_encode($data);
            exit();
        }

        if(!isset($_SESSION['group']) || !isset($c['admin']['groups'][$_SESSION['group']])){
            $data['type'] = 'error';
            $data['message'] = 'Nie masz uprawnień';
            echo json_encode($data);
            exit();
        }
        $sql = @new mysqli($c['mysql']['ip'], $c['mysql']['user'], $c['mysql']['password'], $c['mysql']['database']);

        if($sql->connect_errno){
            $data['type'] = 'error';
            $data['message'] = 'Wystąpił błąd z połączeniem się do bazy!';
            $sql->close();
            echo json_encode($data);
            exit();
        }

        $zap = $sql->query('SELECT * FROM `admins` WHERE `discord_id`="'.$_SESSION['id'].'";');
        if($zap->num_rows == 0){
            $sql->close();
            $data['type'] = 'error';
            $data['message'] = 'Wystąpił nieznany błąd!';
            echo json_encode($data);
            exit();
        }

        $result = @$sql->query('SELECT * FROM `applications` WHERE `id`='.$_POST['id'].';');

        $info = $result->fetch_assoc();
        $data['questions'] = json_decode($info['questions'], true);
        $data['info'] = $info;
        $data['type'] = 'success';
        $data['message'] = 'Załadowano!';
        echo json_encode($data);
        exit();
    }

    if($_POST['type'] == 'accept-waiting-application'){
        if(!isset($_POST['id'])){
            $data['type'] = 'error';
            $data['message'] = 'Nie podałeś ID!';
            echo json_encode($data);
            exit();
        }

        if(!isset($_SESSION['group']) || !isset($c['admin']['groups'][$_SESSION['group']])){
            $data['type'] = 'error';
            $data['message'] = 'Nie masz uprawnień';
            echo json_encode($data);
            exit();
        }

        $sql = @new mysqli($c['mysql']['ip'], $c['mysql']['user'], $c['mysql']['password'], $c['mysql']['database']);

        if($sql->connect_errno){
            $data['type'] = 'error';
            $data['message'] = 'Wystąpił błąd z połączeniem się do bazy!';
            $sql->close();
            echo json_encode($data);
            exit();
        }

        $zap = $sql->query('SELECT * FROM `admins` WHERE `discord_id`="'.$_SESSION['id'].'";');
        if($zap->num_rows == 0){
            $sql->close();
            $data['type'] = 'error';
            $data['message'] = 'Wystąpił nieznany błąd!';
            echo json_encode($data);
            exit();
        }

        $result = @$sql->query('SELECT * FROM `applications` WHERE `id`='.$_POST['id'].';');
        if($result->num_rows > 0){
            $row = $result->fetch_assoc();
            if(!empty($row['checked'])){
                $sql->close();
                $data['type'] = 'information';
                $data['message'] = 'Te podanie zostało już rozpatrzone! ('.$row['status'].')';
                echo json_encode($data);
                exit();
            }
        }

        @$sql->query('UPDATE `applications` SET `date-checked`= CURRENT_TIMESTAMP, `checked` = "'.$_SESSION['username'].'#'.$_SESSION['discriminator'].'", `discord_status` = "accepted", `status` = "conversation" WHERE `id`='.$_POST['id'].';');

        $data['type'] = 'success';
        $data['message'] = 'Zaakceptowano podanie';
        echo json_encode($data);
        exit();
    }

    if($_POST['type'] == 'discard-waiting-application'){
        if(!isset($_POST['id'])){
            $data['type'] = 'error';
            $data['message'] = 'Nie podałeś ID!';
            echo json_encode($data);
            exit();
        }

        if(!isset($_SESSION['group']) || !isset($c['admin']['groups'][$_SESSION['group']])){
            $data['type'] = 'error';
            $data['message'] = 'Nie masz uprawnień';
            echo json_encode($data);
            exit();
        }

        if(in_array('discard_app', $c['admin']['groups'][$_SESSION['group']])){

        } else{
            $data['type'] = 'error';
            $data['message'] = 'Nie masz uprawnień';
            echo json_encode($data);
            exit();
        }

        $sql = @new mysqli($c['mysql']['ip'], $c['mysql']['user'], $c['mysql']['password'], $c['mysql']['database']);

        if($sql->connect_errno){
            $data['type'] = 'error';
            $data['message'] = 'Wystąpił błąd z połączeniem się do bazy!';
            $sql->close();
            echo json_encode($data);
            exit();
        }

        $zap = $sql->query('SELECT * FROM `admins` WHERE `discord_id`="'.$_SESSION['id'].'";');
        if($zap->num_rows == 0){
            $sql->close();
            $data['type'] = 'error';
            $data['message'] = 'Wystąpił nieznany błąd!';
            echo json_encode($data);
            exit();
        }

        $result = @$sql->query('SELECT * FROM `applications` WHERE `id`='.$_POST['id'].';');
        if($result->num_rows > 0){
            $row = $result->fetch_assoc();
            if(!empty($row['checked'])){
                $sql->close();
                $data['type'] = 'information';
                $data['message'] = 'Te podanie zostało już rozpatrzone! ('.$row['status'].')';
                echo json_encode($data);
                exit();
            }
        }

        @$sql->query('UPDATE `applications` SET `date-checked`= CURRENT_TIMESTAMP, `checked` = "'.$_SESSION['username'].'#'.$_SESSION['discriminator'].'", `discord_status` = "rejected", `status` = "rejected" WHERE `id`='.$_POST['id'].';');

        $data['type'] = 'success';
        $data['message'] = 'Odrzucono podanie';
        echo json_encode($data);
        exit();
    }
    if($_POST['type'] == 'recruitmentstage-application'){
        if(!isset($_POST['id'])){
            $data['type'] = 'error';
            $data['message'] = 'Nie podałeś ID!';
            echo json_encode($data);
            exit();
        }

        if(!isset($_SESSION['group']) || !isset($c['admin']['groups'][$_SESSION['group']])){
            $data['type'] = 'error';
            $data['message'] = 'Nie masz uprawnień';
            echo json_encode($data);
            exit();
        }

        if(in_array('have_conversation', $c['admin']['groups'][$_SESSION['group']])){

        } else{
            $data['type'] = 'error';
            $data['message'] = 'Nie masz uprawnień';
            echo json_encode($data);
            exit();
        }

        $sql = @new mysqli($c['mysql']['ip'], $c['mysql']['user'], $c['mysql']['password'], $c['mysql']['database']);

        if($sql->connect_errno){
            $data['type'] = 'error';
            $data['message'] = 'Wystąpił błąd z połączeniem się do bazy!';
            $sql->close();
            echo json_encode($data);
            exit();
        }

        $zap = $sql->query('SELECT * FROM `admins` WHERE `discord_id`="'.$_SESSION['id'].'";');
        if($zap->num_rows == 0){
            $sql->close();
            $data['type'] = 'error';
            $data['message'] = 'Wystąpił nieznany błąd!';
            echo json_encode($data);
            exit();
        }

        $result = @$sql->query('SELECT * FROM `applications` WHERE `id`='.$_POST['id'].';');

        $info = $result->fetch_assoc();
        $data['questions'] = json_decode($info['questions'], true);
        $data['info'] = $info;
        $data['type'] = 'success';
        $data['message'] = 'Załadowano!';
        echo json_encode($data);
        exit();
    }

    if($_POST['type'] == 'accept-recruitmentstage-application'){
        if(!isset($_POST['id'])){
            $data['type'] = 'error';
            $data['message'] = 'Nie podałeś ID!';
            echo json_encode($data);
            exit();
        }

        if(!isset($_SESSION['group']) || !isset($c['admin']['groups'][$_SESSION['group']])){
            $data['type'] = 'error';
            $data['message'] = 'Nie masz uprawnień';
            echo json_encode($data);
            exit();
        }

        if(in_array('have_conversation', $c['admin']['groups'][$_SESSION['group']])){

        } else{
            $data['type'] = 'error';
            $data['message'] = 'Nie masz uprawnień';
            echo json_encode($data);
            exit();
        }

        $sql = @new mysqli($c['mysql']['ip'], $c['mysql']['user'], $c['mysql']['password'], $c['mysql']['database']);

        if($sql->connect_errno){
            $data['type'] = 'error';
            $data['message'] = 'Wystąpił błąd z połączeniem się do bazy!';
            $sql->close();
            echo json_encode($data);
            exit();
        }

        $zap = $sql->query('SELECT * FROM `admins` WHERE `discord_id`="'.$_SESSION['id'].'";');
        if($zap->num_rows == 0){
            $sql->close();
            $data['type'] = 'error';
            $data['message'] = 'Wystąpił nieznany błąd!';
            echo json_encode($data);
            exit();
        }

        $result = @$sql->query('SELECT * FROM applications WHERE `id`='.$_POST['id'].';');
        if($result->num_rows > 0){
            $row = $result->fetch_assoc();
            if(!empty($row['conductedconversation'])){
                $sql->close();
                $data['type'] = 'information';
                $data['message'] = 'Na tym użytkowniku została już przeprowadzona rozmowa! ('.$row['status'].')';
                echo json_encode($data);
                exit();
            }
        }

        @$sql->query('UPDATE `applications` SET `date-conductedconversation`= CURRENT_TIMESTAMP, `conductedconversation` = "'.$_SESSION['username'].'#'.$_SESSION['discriminator'].'", `discord_status` = "accepted-conversation", `status` = "accepted" WHERE `id`='.$_POST['id'].';');

        $data['type'] = 'success';
        $data['message'] = 'Zdana rozmowa';
        echo json_encode($data);
        exit();
    }
    if($_POST['type'] == 'discard-recruitmentstage-application'){
        if(!isset($_POST['id'])){
            $data['type'] = 'error';
            $data['message'] = 'Nie podałeś ID!';
            echo json_encode($data);
            exit();
        }

        if(!isset($_SESSION['group']) || !isset($c['admin']['groups'][$_SESSION['group']])){
            $data['type'] = 'error';
            $data['message'] = 'Nie masz uprawnień';
            echo json_encode($data);
            exit();
        }

        if(in_array('have_conversation', $c['admin']['groups'][$_SESSION['group']])){

        } else{
            $data['type'] = 'error';
            $data['message'] = 'Nie masz uprawnień';
            echo json_encode($data);
            exit();
        }

        $sql = @new mysqli($c['mysql']['ip'], $c['mysql']['user'], $c['mysql']['password'], $c['mysql']['database']);

        if($sql->connect_errno){
            $data['type'] = 'error';
            $data['message'] = 'Wystąpił błąd z połączeniem się do bazy!';
            $sql->close();
            echo json_encode($data);
            exit();
        }

        $zap = $sql->query('SELECT * FROM `admins` WHERE `discord_id`="'.$_SESSION['id'].'";');
        if($zap->num_rows == 0){
            $sql->close();
            $data['type'] = 'error';
            $data['message'] = 'Wystąpił nieznany błąd!';
            echo json_encode($data);
            exit();
        }

        $result = @$sql->query('SELECT * FROM applications WHERE `id`='.$_POST['id'].';');
        if($result->num_rows > 0){
            $row = $result->fetch_assoc();
            if(!empty($row['conductedconversation'])){
                $sql->close();
                $data['type'] = 'information';
                $data['message'] = 'Na tym użytkowniku została już przeprowadzona rozmowa! ('.$row['status'].')';
                echo json_encode($data);
                exit();
            }
        }

        @$sql->query('UPDATE `applications` SET `date-conductedconversation`= CURRENT_TIMESTAMP, `conductedconversation` = "'.$_SESSION['username'].'#'.$_SESSION['discriminator'].'", `discord_status` = "discard-conversation", `status` = "rejected" WHERE `id`='.$_POST['id'].';');

        $data['type'] = 'success';
        $data['message'] = 'Niezdana rozmowa';
        echo json_encode($data);
        exit();
    }

    if($_POST['type'] == 'show-application'){
        if(!isset($_POST['id'])){
            $data['type'] = 'error';
            $data['message'] = 'Nie podałeś ID!';
            echo json_encode($data);
            exit();
        }

        if(!isset($_SESSION['group']) || !isset($c['admin']['groups'][$_SESSION['group']])){
            $data['type'] = 'error';
            $data['message'] = 'Nie masz uprawnień';
            echo json_encode($data);
            exit();
        }

        if(in_array('accept_app', $c['admin']['groups'][$_SESSION['group']]) || in_array('discard_app', $c['admin']['groups'][$_SESSION['group']])){

        } else{
            $data['type'] = 'error';
            $data['message'] = 'Nie masz uprawnień';
            echo json_encode($data);
            exit();
        }

        $sql = @new mysqli($c['mysql']['ip'], $c['mysql']['user'], $c['mysql']['password'], $c['mysql']['database']);

        if($sql->connect_errno){
            $data['type'] = 'error';
            $data['message'] = 'Wystąpił błąd z połączeniem się do bazy!';
            $sql->close();
            echo json_encode($data);
            exit();
        }

        $zap = $sql->query('SELECT * FROM `admins` WHERE `discord_id`="'.$_SESSION['id'].'";');
        if($zap->num_rows == 0){
            $sql->close();
            $data['type'] = 'error';
            $data['message'] = 'Wystąpił nieznany błąd!';
            echo json_encode($data);
            exit();
        }

        $result = @$sql->query('SELECT * FROM `applications` WHERE `id`='.$_POST['id'].';');

        $info = $result->fetch_assoc();
        $data['questions'] = json_decode($info['questions'], true);
        $data['info'] = $info;
        $data['type'] = 'success';
        $data['message'] = 'Załadowano!';
        echo json_encode($data);
        exit();
    }

    if($_POST['type'] == 'delete-application'){
        if(!isset($_POST['id'])){
            $data['type'] = 'error';
            $data['message'] = 'Nie podałeś ID!';
            echo json_encode($data);
            exit();
        }

        if(!isset($_SESSION['group']) || !isset($c['admin']['groups'][$_SESSION['group']])){
            $data['type'] = 'error';
            $data['message'] = 'Nie masz uprawnień';
            echo json_encode($data);
            exit();
        }

        if(in_array('remove_app', $c['admin']['groups'][$_SESSION['group']])){

        } else{
            $data['type'] = 'error';
            $data['message'] = 'Nie masz uprawnień';
            echo json_encode($data);
            exit();
        }

        $sql = @new mysqli($c['mysql']['ip'], $c['mysql']['user'], $c['mysql']['password'], $c['mysql']['database']);

        if($sql->connect_errno){
            $data['type'] = 'error';
            $data['message'] = 'Wystąpił błąd z połączeniem się do bazy!';
            $sql->close();
            echo json_encode($data);
            exit();
        }

        $zap = $sql->query('SELECT * FROM `admins` WHERE `discord_id`="'.$_SESSION['id'].'";');
        if($zap->num_rows == 0){
            $sql->close();
            $data['type'] = 'error';
            $data['message'] = 'Wystąpił nieznany błąd!';
            echo json_encode($data);
            exit();
        }

        $result = @$sql->query('DELETE FROM `applications` WHERE `id`='.mysqli_real_escape_string($sql, $_POST['id']).';');
        $data['type'] = 'success';
        $data['message'] = 'Usunięto podanie (ID: '.$_POST['id'].')!';
        echo json_encode($data);
        exit();
    }

    if($_POST['type'] == 'add-admin'){
        if(!isset($_POST['name']) || !isset($_POST['discordid']) || !isset($_POST['group'])){
            $data['type'] = 'error';
            $data['message'] = 'Wypełnij wszystkie pola!';
            echo json_encode($data);
            exit();
        }

        if(!isset($_SESSION['group']) || !isset($c['admin']['groups'][$_SESSION['group']])){
            $data['type'] = 'error';
            $data['message'] = 'Nie masz uprawnień';
            echo json_encode($data);
            exit();
        }

        if(in_array('add_admins', $c['admin']['groups'][$_SESSION['group']])){

        } else{
            $data['type'] = 'error';
            $data['message'] = 'Nie masz uprawnień';
            echo json_encode($data);
            exit();
        }

        $sql = @new mysqli($c['mysql']['ip'], $c['mysql']['user'], $c['mysql']['password'], $c['mysql']['database']);

        if($sql->connect_errno){
            $data['type'] = 'error';
            $data['message'] = 'Wystąpił błąd z połączeniem się do bazy!';
            $sql->close();
            echo json_encode($data);
            exit();
        }

        $sql->query('INSERT INTO `admins` (`discord_name`, `discord_id`, `group`) VALUES ("'.mysqli_real_escape_string($sql, $_POST['name']).'", "'.mysqli_real_escape_string($sql, $_POST['discordid']).'", "'.mysqli_real_escape_string($sql, $_POST['group']).'");');

        $data['type'] = 'success';
        $data['message'] = 'Dodano administratora!';
        echo json_encode($data);
        exit();
    }

    if($_POST['type'] == 'delete-admin'){
        if(!isset($_POST['id'])){
            $data['type'] = 'error';
            $data['message'] = 'Nie podałeś ID!';
            echo json_encode($data);
            exit();
        }

        if(!isset($_SESSION['group']) || !isset($c['admin']['groups'][$_SESSION['group']])){
            $data['type'] = 'error';
            $data['message'] = 'Nie masz uprawnień';
            echo json_encode($data);
            exit();
        }

        if(in_array('remove_admins', $c['admin']['groups'][$_SESSION['group']])){

        } else{
            $data['type'] = 'error';
            $data['message'] = 'Nie masz uprawnień';
            echo json_encode($data);
            exit();
        }

        $sql = @new mysqli($c['mysql']['ip'], $c['mysql']['user'], $c['mysql']['password'], $c['mysql']['database']);

        if($sql->connect_errno){
            $data['type'] = 'error';
            $data['message'] = 'Wystąpił błąd z połączeniem się do bazy!';
            $sql->close();
            echo json_encode($data);
            exit();
        }

        @$sql->query('DELETE FROM `admins` WHERE `id`='.mysqli_real_escape_string($sql, $_POST['id']).';');

        $data['type'] = 'success';
        $data['message'] = 'Usunięto administratora!';
        echo json_encode($data);
        exit();
    }

    if($_POST['type'] == 'add-to-block'){
        if(!isset($_POST['id'])){
            $data['type'] = 'error';
            $data['message'] = 'Nie podałeś ID!';
            echo json_encode($data);
            exit();
        }

        if(!isset($_SESSION['group']) || !isset($c['admin']['groups'][$_SESSION['group']])){
            $data['type'] = 'error';
            $data['message'] = 'Nie masz uprawnień';
            echo json_encode($data);
            exit();
        }

        if(in_array('add_to_block', $c['admin']['groups'][$_SESSION['group']])){

        } else{
            $data['type'] = 'error';
            $data['message'] = 'Nie masz uprawnień';
            echo json_encode($data);
            exit();
        }

        $sql = @new mysqli($c['mysql']['ip'], $c['mysql']['user'], $c['mysql']['password'], $c['mysql']['database']);

        if($sql->connect_errno){
            $data['type'] = 'error';
            $data['message'] = 'Wystąpił błąd z połączeniem się do bazy!';
            $sql->close();
            echo json_encode($data);
            exit();
        }

        $zap = $sql->query('SELECT * FROM `applications` WHERE `id`="'.mysqli_real_escape_string($sql, $_POST['id']).'";');
        if($zap->num_rows == 0){
            $sql->close();
            $data['type'] = 'error';
            $data['message'] = 'Wystąpił nieznany błąd!';
            echo json_encode($data);
            exit();
        }
        $row = $zap->fetch_assoc();
        $user = $sql->query('SELECT * FROM `blocked` WHERE `discord_id`="'.$row['discord_id'].'";');
        if($user->num_rows > 0){
            $sql->close();
            $data['type'] = 'error';
            $data['message'] = 'Ten użytkownik znajduję się już na liście zablokowanych!';
            echo json_encode($data);
            exit();
        }

        @$sql->query('INSERT INTO `blocked` VALUES ("'.$row['discord_id'].'", "'.$row['discord_username'].'", "'.$row['discord_discriminator'].'");');

        $data['type'] = 'success';
        $data['message'] = 'Dodano '.$row['discord_username'].'#'.$row['discord_discriminator'].' do listy zablokowanych';
        echo json_encode($data);
        exit();
    }

    if($_POST['type'] == 'remove-from-block'){
        if(!isset($_POST['id'])){
            $data['type'] = 'error';
            $data['message'] = 'Nie podałeś ID!';
            echo json_encode($data);
            exit();
        }

        if(!isset($_SESSION['group']) || !isset($c['admin']['groups'][$_SESSION['group']])){
            $data['type'] = 'error';
            $data['message'] = 'Nie masz uprawnień';
            echo json_encode($data);
            exit();
        }

        if(in_array('remove_from_block', $c['admin']['groups'][$_SESSION['group']])){

        } else{
            $data['type'] = 'error';
            $data['message'] = 'Nie masz uprawnień';
            echo json_encode($data);
            exit();
        }

        $sql = @new mysqli($c['mysql']['ip'], $c['mysql']['user'], $c['mysql']['password'], $c['mysql']['database']);

        if($sql->connect_errno){
            $data['type'] = 'error';
            $data['message'] = 'Wystąpił błąd z połączeniem się do bazy!';
            $sql->close();
            echo json_encode($data);
            exit();
        }

        @$sql->query('DELETE FROM `blocked` WHERE `discord_id` = "'.mysqli_real_escape_string($sql, $_POST['id']).'";');

        $data['type'] = 'success';
        $data['message'] = 'Usunięto użytkownika z listy zablokowanych';
        echo json_encode($data);
        exit();
    }
}

?>