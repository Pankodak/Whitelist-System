<?php

ini_set('session.gc_maxlifetime', 2*60*60); // 2 hours
session_start();
require_once 'vendor/autoload.php';
require_once 'cfg.php';

$provider = new \Discord\OAuth\Discord([
    'clientId' => $c['oauth2']['client_id'],
    'clientSecret' => $c['oauth2']['client_secret'],
    'redirectUri' => $c['oauth2']['redirectUri_admin']
]);

if(isset($_GET['error'])){
    if($_GET['error'] == 'access_denied'){
        $_SESSION['error'] = 'Właściciel zasobu lub serwer autoryzacyjny odmówił żądania. Spróbuj zalogować się jeszcze raz.';
        header('Location: index.php');
        exit();
    }
}

if(isset($_SESSION['islogged'])){
    $sql = @new mysqli($c['mysql']['ip'], $c['mysql']['user'], $c['mysql']['password'], $c['mysql']['database']);
    if($sql->connect_errno){
        header('Location: index.php');
        $sql->close();
        exit();
    }
    $query = $sql->query('SELECT * FROM `admins` WHERE `discord_id`="'.$_SESSION['id'].'";');
    if($query->num_rows == 0){
        session_destroy();
        session_unset();
        $sql->close();
        session_start();
        $_SESSION['error'] = 'Nie masz dostępu do panelu administratora';
        header('Location: index.php');
        exit();
    }
    $row = $query->fetch_assoc();
    $_SESSION['group'] = $row['group'];
    $sql->close();
} else{
    if(isset($_GET['code'])){
        $sql = @new mysqli($c['mysql']['ip'], $c['mysql']['user'], $c['mysql']['password'], $c['mysql']['database']);
        if($sql->connect_errno){
            $_SESSION['error'] = 'Występił błąd z połączeniem się do bazy danych';
            header('Location: index.php');
            $sql->close();
            exit();
        }
        $sql->close();
        $token = $provider->getAccessToken('authorization_code', [
            'code' => $_GET['code'],
        ]);
        $user = $provider->getResourceOwner($token);
        #$invite = $user->acceptInvite($c['oauth2']['inviteLink']);
        $_SESSION['islogged'] = true;
        $_SESSION['id'] = $user->id;
        $_SESSION['username'] = $user->username;
        $_SESSION['email'] = $user->email;
        $_SESSION['discriminator'] = $user->discriminator;
        $_SESSION['avatar'] = $user->avatar;
        $_SESSION['verified'] = $user->verified;
        $_SESSION['mfa_enabled'] = $user->mfa_enabled;
        header('Location: admin.php');
        exit();
    } else{
        header('Location: '.$provider->getAuthorizationUrl(['scope' => ['identify', 'email']]));
        exit();
    }
}

?>

<!DOCTYPE html>
<html lang="pl">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="X-UA-Compatible" content="ie=edge">
        <title>Panel administacyjny <?= $c['surfix'] ?></title>
        <link rel="icon" type="image/ico" href="img/logoglowna.png" />
        <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.2/css/all.css" integrity="sha384-oS3vJWv+0UjzBfQzYUhtDYW+Pj2yciDJxpsK1OYPAYjqT085Qq/1cq5FLXAZQ7Ay" crossorigin="anonymous">
        <link href="https://fonts.googleapis.com/css?family=Roboto:400,700&amp;subset=latin-ext" rel="stylesheet">
        <link rel="stylesheet" href="./assets/css/loader.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/3.7.0/animate.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/noty/3.1.4/noty.css">
        <link rel="stylesheet" href="./assets/css/themes/metroui.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.19/css/jquery.dataTables.css">
        <link rel="stylesheet" href="./assets/css/datatables.css">
        <link rel="stylesheet" href="./assets/css/admin.css">
        <style>
        table{
            font-size: 19px;
            font-weight: bold;
        }
        .container{
            width: 1100px;
        }
        .margin-btn{
            margin-right: 10px;
        }
        </style>
    </head>
    <body>
        <noscript>Jeżeli chcesz używać tej strony musisz mieć właczonego JavaScripta</noscript>
        <div id="loader" class="loader">
			<div class="lds-ring"><div></div><div></div><div></div><div></div></div>
        </div>
        <a class="przyciskdwa" href="./index.php"><i class="fas fa-arrow-left"></i></a>
        <div class="logo"><?=$c['name']?> - Panel administatora<br />
        <div style="margin-top: 10px;">
            <a href="./index.php?logout" id="btn_logout" style="float:left;">Wyloguj się</a>
            <?php if(in_array('add_admins', $c['admin']['groups'][$_SESSION['group']]) || in_array('remove_admins', $c['admin']['groups'][$_SESSION['group']])){?>
                <a href="#" class="admins" style="float:left;" onclick="openmanageadmins();">Zarządzaj administratorami</a>
            <?php } ?>
            <div style="clear: both;"></div>
        </div>
        </div>
        <div class="container">
            <div class="row">
                <div class="col s12">
                    <ul class="tabs" style="margin-left:auto;margin-right:auto;">
                        <li class="tab col s2"><a class="active" href="#waiting" onclick="refresh(false, 'waiting');">Oczekujące</a></li>
                        <li class="tab col s3"><a href="#recruitmentstage" onclick="refresh(false, 'recruitmentstage');">Etap rekrutacyjny</a></li>
                        <li class="tab col s3"><a href="#accepted" onclick="refresh(false, 'accepted');">Zaakceptowane</a></li>
                        <li class="tab col s2"><a href="#rejected" onclick="refresh(false, 'rejected');">Odrzucone</a></li>
                        <li class="tab col s2"><a href="#blocked" onclick="refresh(false, 'blocked');">Zablokowani</a></li>
                    </ul>
                </div>
                <div id="waiting" class="col s12">
                    <h5>Lista oczekujących</h5><button type="button" class="refresh waves-effect waves-light btn" onclick="refresh(true, 'waiting');">Odśwież</button>
                    <table id="waiting-table" class="tbl1">
                        <thead>
                            <tr>
                                <td>ID</td>
                                <td>NICK#ID</td>
                                <td>Data wysłania</td>
                                <td>Operacja</td>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
                <div id="recruitmentstage" class="col s12">
                    <h5>Lista osób do rozmowy</h5><button type="button" class="refresh waves-effect waves-light btn" onclick="refresh(true, 'recruitmentstage');">Odśwież</button>
                    <table id="recruitmentstage-table" class="tbl1" style="width:100%">
                        <thead>
                            <tr>
                                <td>ID</td>
                                <td>NICK#ID</td>
                                <td>Data wysłania</td>
                                <td>Data sprawdzenia</td>
                                <td>Sprawdzający</td>
                                <td>Operacja</td>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
                <div id="accepted" class="col s12">
                    <h5>Lista zaakceptowanych</h5><button type="button" class="refresh waves-effect waves-light btn" onclick="refresh(true, 'accepted');">Odśwież</button>
                    <table id="accepted-table" class="tbl1" style="width:100%">
                        <thead>
                            <tr>
                                <td>ID</td>
                                <td>NICK#ID</td>
                                <td>Data wysłania</td>
                                <td>Data sprawdzenia</td>
                                <td>Sprawdzający</td>
                                <td>Operacja</td>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
                <div id="rejected" class="col s12">
                    <h5>Lista odrzuconych</h5><button type="button" class="refresh waves-effect waves-light btn" onclick="refresh(true, 'rejected');">Odśwież</button>
                    <table id="rejected-table" class="tbl1" style="width:100%">
                        <thead>
                            <tr>
                                <td>ID</td>
                                <td>NICK#ID</td>
                                <td>Data wysłania</td>
                                <td>Data sprawdzenia</td>
                                <td>Sprawdzający</td>
                                <td>Operacja</td>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
                <div id="blocked" class="col s12">
                    <?php if(in_array('add_to_block', $c['admin']['groups'][$_SESSION['group']]) || in_array('remove_from_block', $c['admin']['groups'][$_SESSION['group']])){?>
                        <h5>Lista zablokowanych</h5><button type="button" class="refresh waves-effect waves-light btn" onclick="refresh(true, 'blocked');">Odśwież</button>
                        <table id="blocked-table" class="tbl1" style="width:100%">
                            <thead>
                                <tr>
                                    <td>Discord ID</td>
                                    <td>NICK#ID</td>
                                    <td>Operacja</td>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    <?php } ?>
                </div>
            </div>
        </div>
        <!-- Modal Structure -->
        <div id="waiting-modal" class="modal modal-fixed-footer">
            <div class="modal-content">
                <h4 id="title"></h4>
                <div id="info"></div>
                <input type="hidden" id="id" name="id" value="" />
                <div class="questions"></div>
            </div>
            <div class="modal-footer">
                <?php if(in_array('add_to_block', $c['admin']['groups'][$_SESSION['group']])){?>
                    <button type="button" id="addblock" class="modal-close waves-effect waves-light btn" style="background-color: red;">Dodaj do zablokowanych</button>
                <?php } ?>
                <?php if(in_array('remove_app', $c['admin']['groups'][$_SESSION['group']])){?>
                    <button type="button" id="deleteapp" class="modal-close waves-effect waves-light btn" style="background-color: red;">Usuń aplikację</button>
                <?php } ?>
                <a href="#!" class="modal-close waves-effect waves-light btn" style="background-color: red;">Anuluj</a>
                <button type="button" id="discard" class="modal-close waves-effect waves-light btn" style="background-color: red;">Odrzuć</button>
                <button type="button" id="accept" class="modal-close waves-effect waves-light btn" style="background-color: green;">Akceptuj</button>
            </div>
        </div>
        <div id="show-application" class="modal modal-fixed-footer">
            <div class="modal-content">
                <h4 id="title"></h4>
                <div id="info"></div>
                <input type="hidden" id="id" name="id" value="" />
                <div class="questions"></div>
            </div>
            <div class="modal-footer">
                <?php if(in_array('add_to_block', $c['admin']['groups'][$_SESSION['group']])){?>
                    <button type="button" id="addblock" class="modal-close waves-effect waves-light btn" style="background-color: red;">Dodaj do zablokowanych</button>
                <?php } ?>
                <?php if(in_array('remove_app', $c['admin']['groups'][$_SESSION['group']])){?>
                    <button type="button" id="deleteapp" class="modal-close waves-effect waves-light btn" style="background-color: red;">Usuń aplikację</button>
                <?php } ?>
                <a href="#!" class="modal-close waves-effect waves-light btn" style="background-color: red;">Anuluj</a>
            </div>
        </div>
        <div id="recruitmentstage-application" class="modal modal-fixed-footer">
            <div class="modal-content">
                <h4 id="title"></h4>
                <div id="info"></div>
                <input type="hidden" id="id" name="id" value="" />
                <div class="questions"></div>
            </div>
            <div class="modal-footer">
                <?php if(in_array('add_to_block', $c['admin']['groups'][$_SESSION['group']])){?>
                    <button type="button" id="addblock" class="modal-close waves-effect waves-light btn" style="background-color: red;">Dodaj do zablokowanych</button>
                <?php } ?>
                <?php if(in_array('remove_app', $c['admin']['groups'][$_SESSION['group']])){?>
                    <button type="button" id="deleteapp" class="modal-close waves-effect waves-light btn" style="background-color: red;">Usuń aplikację</button>
                <?php } ?>
                <a href="#!" class="modal-close waves-effect waves-light btn" style="background-color: red;">Anuluj</a>
                <button type="button" id="discard" class="modal-close waves-effect waves-light btn" style="background-color: red;">Rozmowa niezdana</button>
                <button type="button" id="accept" class="modal-close waves-effect waves-light btn" style="background-color: green;">Rozmowa zdana</button>
            </div>
        </div>
        <?php if(in_array('add_admins', $c['admin']['groups'][$_SESSION['group']]) || in_array('remove_admins', $c['admin']['groups'][$_SESSION['group']])){?>
            <div id="manage-admins" class="modal modal-fixed-footer">
                <div class="modal-content">
                    <h4>Zarządzanie administratorami</h4>
                    <h5>Dodaj administratora</h5>
                    <div class="actions">
                        <input type="text" name="name" id="name" placeholder="Nick" style="width: 24%;float:left;">
                        <input type="text" name="discord-id" id="discord-id" placeholder="Discord ID" style="width: 25%;float:left;margin-left: 10px;">
                        <select name="group" id="group" style="width: 23%;float:left;display:block;;margin-left: 10px;">
                            <?php
                            foreach($c['admin']['groups'] as $key => $value){
                                echo '<option value="'.$key.'">'.$key.'</option>';
                            }
                            ?>
                        </select>
                        <button type="button" style="width: 22%;float:left;margin-left: 10px;" id="add-admin">Dodaj</button>
                        <div style="clear: both;"></div>
                    </div>
                    <h5>Lista administratorów</h5><button type="button" class="refresh waves-effect waves-light btn" onclick="refresh(true, 'admins');">Odśwież</button>
                    <table id="admins" class="tbl1" style="width:100%">
                        <thead>
                            <tr>
                                <td>Nick</td>
                                <td>DiscordID</td>
                                <td>Grupa</td>
                                <td>Operacja</td>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
                <div class="modal-footer">
                    <a href="#!" class="modal-close waves-effect waves-light btn" style="background-color: red;">Zamknij</a>
                </div>
            </div>
        <?php }?>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.19/js/jquery.dataTables.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/noty/3.1.4/noty.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
        <script>
            $(document).ready(function(){
                $('.tabs').tabs();
                $('#waiting-modal, #show-application, #recruitmentstage-application, #manage-admins').modal();
                $('#waiting-table, #accepted-table, #rejected-table, #recruitmentstage-table, #admins, #blocked-table').DataTable({
                    "bLengthChange": false,
                    "language": {
                        "processing":     "Przetwarzanie...",
                        "search":         "Szukaj:",
                        "lengthMenu":     "Pokaż _MENU_ pozycji",
                        "info":           "Pozycje od _START_ do _END_ z _TOTAL_ łącznie",
                        "infoEmpty":      "Pozycji 0 z 0 dostępnych",
                        "infoFiltered":   "(filtrowanie spośród _MAX_ dostępnych pozycji)",
                        "infoPostFix":    "",
                        "loadingRecords": "Wczytywanie...",
                        "zeroRecords":    "Nie znaleziono pasujących pozycji",
                        "emptyTable":     "Brak danych",
                        "paginate": {
                            "first":      "Pierwsza",
                            "previous":   "Poprzednia",
                            "next":       "Następna",
                            "last":       "Ostatnia"
                        },
                        "aria": {
                            "sortAscending": ": aktywuj, by posortować kolumnę rosnąco",
                            "sortDescending": ": aktywuj, by posortować kolumnę malejąco"
                        }
                    }
                });
                refresh(false, 'all');
                $('#loader').fadeOut(250);
            });
            $(window).bind('beforeunload', () => {
                    $("#loader").fadeIn(250);
            });
            $(window).on('unload', () => {
                $("#loader").fadeIn(250);
            });
            function refresh(noty, option){
                if(option == 'waiting' || option == 'all'){
                    $.ajax({
                        url: 'ajax.php',
                        type: 'POST',
                        data: {
                            type: 'admin-waiting'
                        },
                        dataType: 'json',
                        success: function(data){
                            //console.log(data);
                            if(data.type == 'error'){
                                new Noty({
                                    layout: 'topRight',
                                    theme: 'metroui',
                                    type: data.type,
                                    text: data.message,
                                    timeout: 3000,
                                    progressBar: false,
                                    animation: {
                                        open: 'animated fadeInDown',
                                        close: 'animated fadeOutUp'
                                    }
                                }).show();
                                $('.refresh').prop("disabled", false);
                                return;
                            }
                            $('#waiting-table').DataTable().clear().draw();
                            try{
                                $('#waiting-table').DataTable().rows.add(data.list).draw();
                            } catch(e){
                                console.log(e);
                            }
                            if(noty == true){
                                new Noty({
                                    layout: 'topRight',
                                    theme: 'metroui',
                                    type: data.type,
                                    text: data.message,
                                    timeout: 5000,
                                    progressBar: false,
                                    animation: {
                                        open: 'animated fadeInDown',
                                        close: 'animated fadeOutUp'
                                    }
                                }).show();
                            }
                            $('.refresh').prop("disabled", false);
                            return;
                        },
                        error: function(data){
                            console.log(data);
                            new Noty({
                                layout: 'topRight',
                                theme: 'metroui',
                                type: 'error',
                                text: 'Wystąpił błąd (' + data + ')',
                                timeout: 5000,
                                progressBar: false,
                                animation: {
                                    open: 'animated fadeInDown',
                                    close: 'animated fadeOutUp'
                                }
                            }).show();
                            $('.refresh').prop("disabled", false);
                            return;
                        }
                    });
                }
                if(option == 'accepted' || option == 'all'){
                    $.ajax({
                        url: 'ajax.php',
                        type: 'POST',
                        data: {
                            type: 'admin-accepted'
                        },
                        dataType: 'json',
                        success: function(data){
                            //console.log(data);
                            if(data.type == 'error'){
                                new Noty({
                                    layout: 'topRight',
                                    theme: 'metroui',
                                    type: data.type,
                                    text: data.message,
                                    timeout: 3000,
                                    progressBar: false,
                                    animation: {
                                        open: 'animated fadeInDown',
                                        close: 'animated fadeOutUp'
                                    }
                                }).show();
                                $('.refresh').prop("disabled", false);
                                return;
                            }
                            $('#accepted-table').DataTable().clear().draw();
                            try{
                                $('#accepted-table').DataTable().rows.add(data.list).draw();
                            } catch(e){
                                console.log(e);
                            }
                            if(noty == true){
                                new Noty({
                                    layout: 'topRight',
                                    theme: 'metroui',
                                    type: data.type,
                                    text: data.message,
                                    timeout: 5000,
                                    progressBar: false,
                                    animation: {
                                        open: 'animated fadeInDown',
                                        close: 'animated fadeOutUp'
                                    }
                                }).show();
                            }
                            $('.refresh').prop("disabled", false);
                            return;
                        },
                        error: function(data){
                            console.log(data);
                            new Noty({
                                layout: 'topRight',
                                theme: 'metroui',
                                type: 'error',
                                text: 'Wystąpił błąd (' + data + ')',
                                timeout: 5000,
                                progressBar: false,
                                animation: {
                                    open: 'animated fadeInDown',
                                    close: 'animated fadeOutUp'
                                }
                            }).show();
                            $('.refresh').prop("disabled", false);
                            return;
                        }
                    });
                }
                if(option == 'rejected' || option == 'all'){
                    $.ajax({
                        url: 'ajax.php',
                        type: 'POST',
                        data: {
                            type: 'admin-rejected'
                        },
                        dataType: 'json',
                        success: function(data){
                            //console.log(data);
                            if(data.type == 'error'){
                                new Noty({
                                    layout: 'topRight',
                                    theme: 'metroui',
                                    type: data.type,
                                    text: data.message,
                                    timeout: 3000,
                                    progressBar: false,
                                    animation: {
                                        open: 'animated fadeInDown',
                                        close: 'animated fadeOutUp'
                                    }
                                }).show();
                                $('.refresh').prop("disabled", false);
                                return;
                            }
                            $('#rejected-table').DataTable().clear().draw();
                            try{
                                $('#rejected-table').DataTable().rows.add(data.list).draw();
                            } catch(e){
                                console.log(e);
                            }
                            if(noty == true){
                                new Noty({
                                    layout: 'topRight',
                                    theme: 'metroui',
                                    type: data.type,
                                    text: data.message,
                                    timeout: 5000,
                                    progressBar: false,
                                    animation: {
                                        open: 'animated fadeInDown',
                                        close: 'animated fadeOutUp'
                                    }
                                }).show();
                            }
                            $('.refresh').prop("disabled", false);
                            return;
                        },
                        error: function(data){
                            console.log(data);
                            new Noty({
                                layout: 'topRight',
                                theme: 'metroui',
                                type: 'error',
                                text: 'Wystąpił błąd (' + data + ')',
                                timeout: 5000,
                                progressBar: false,
                                animation: {
                                    open: 'animated fadeInDown',
                                    close: 'animated fadeOutUp'
                                }
                            }).show();
                            $('.refresh').prop("disabled", false);
                            return;
                        }
                    });
                }
                if(option == 'recruitmentstage' || option == 'all'){
                    $.ajax({
                        url: 'ajax.php',
                        type: 'POST',
                        data: {
                            type: 'admin-recruitmentstage'
                        },
                        dataType: 'json',
                        success: function(data){
                            //console.log(data);
                            if(data.type == 'error'){
                                new Noty({
                                    layout: 'topRight',
                                    theme: 'metroui',
                                    type: data.type,
                                    text: data.message,
                                    timeout: 3000,
                                    progressBar: false,
                                    animation: {
                                        open: 'animated fadeInDown',
                                        close: 'animated fadeOutUp'
                                    }
                                }).show();
                                $('.refresh').prop("disabled", false);
                                return;
                            }
                            $('#recruitmentstage-table').DataTable().clear().draw();
                            try{
                                $('#recruitmentstage-table').DataTable().rows.add(data.list).draw();
                            } catch(e){
                                console.log(e);
                            }
                            if(noty == true){
                                new Noty({
                                    layout: 'topRight',
                                    theme: 'metroui',
                                    type: data.type,
                                    text: data.message,
                                    timeout: 5000,
                                    progressBar: false,
                                    animation: {
                                        open: 'animated fadeInDown',
                                        close: 'animated fadeOutUp'
                                    }
                                }).show();
                            }
                            $('.refresh').prop("disabled", false);
                            return;
                        },
                        error: function(data){
                            console.log(data);
                            new Noty({
                                layout: 'topRight',
                                theme: 'metroui',
                                type: 'error',
                                text: 'Wystąpił błąd (' + data + ')',
                                timeout: 5000,
                                progressBar: false,
                                animation: {
                                    open: 'animated fadeInDown',
                                    close: 'animated fadeOutUp'
                                }
                            }).show();
                            $('.refresh').prop("disabled", false);
                            return;
                        }
                    });
                }
                if(option == 'admins'){
                    $.ajax({
                        url: 'ajax.php',
                        type: 'POST',
                        data: {
                            type: 'admin-admins'
                        },
                        dataType: 'json',
                        success: function (data) {
                            //console.log(data);
                            if (data.type == 'error') {
                                new Noty({
                                    layout: 'topRight',
                                    theme: 'metroui',
                                    type: data.type,
                                    text: data.message,
                                    timeout: 3000,
                                    progressBar: false,
                                    animation: {
                                        open: 'animated fadeInDown',
                                        close: 'animated fadeOutUp'
                                    }
                                }).show();
                                $('.refresh').prop("disabled", false);
                                return;
                            }
                            $('#admins').DataTable().clear().draw();
                            try {
                                $('#admins').DataTable().rows.add(data.list).draw();
                            } catch (e) {
                                console.log(e);
                            }
                            if (noty == true) {
                                new Noty({
                                    layout: 'topRight',
                                    theme: 'metroui',
                                    type: data.type,
                                    text: data.message,
                                    timeout: 5000,
                                    progressBar: false,
                                    animation: {
                                        open: 'animated fadeInDown',
                                        close: 'animated fadeOutUp'
                                    }
                                }).show();
                            }
                            $('.refresh').prop("disabled", false);
                            return;
                        },
                        error: function (data) {
                            console.log(data);
                            new Noty({
                                layout: 'topRight',
                                theme: 'metroui',
                                type: 'error',
                                text: 'Wystąpił błąd (' + data + ')',
                                timeout: 5000,
                                progressBar: false,
                                animation: {
                                    open: 'animated fadeInDown',
                                    close: 'animated fadeOutUp'
                                }
                            }).show();
                            $('.refresh').prop("disabled", false);
                            return;
                        }
                    });
                }
                <?php if(in_array('add_to_block', $c['admin']['groups'][$_SESSION['group']]) || in_array('remove_from_block', $c['admin']['groups'][$_SESSION['group']])){?>
                    if(option == 'blocked' || option == 'all'){
                        $.ajax({
                            url: 'ajax.php',
                            type: 'POST',
                            data: {
                                type: 'admin-blocked'
                            },
                            dataType: 'json',
                            success: function(data){
                                //console.log(data);
                                if(data.type == 'error'){
                                    new Noty({
                                        layout: 'topRight',
                                        theme: 'metroui',
                                        type: data.type,
                                        text: data.message,
                                        timeout: 3000,
                                        progressBar: false,
                                        animation: {
                                            open: 'animated fadeInDown',
                                            close: 'animated fadeOutUp'
                                        }
                                    }).show();
                                    $('.refresh').prop("disabled", false);
                                    return;
                                }
                                $('#blocked-table').DataTable().clear().draw();
                                try{
                                    $('#blocked-table').DataTable().rows.add(data.list).draw();
                                } catch(e){
                                    console.log(e);
                                }
                                if(noty == true){
                                    new Noty({
                                        layout: 'topRight',
                                        theme: 'metroui',
                                        type: data.type,
                                        text: data.message,
                                        timeout: 5000,
                                        progressBar: false,
                                        animation: {
                                            open: 'animated fadeInDown',
                                            close: 'animated fadeOutUp'
                                        }
                                    }).show();
                                }
                                $('.refresh').prop("disabled", false);
                                return;
                            },
                            error: function(data){
                                console.log(data);
                                new Noty({
                                    layout: 'topRight',
                                    theme: 'metroui',
                                    type: 'error',
                                    text: 'Wystąpił błąd (' + data + ')',
                                    timeout: 5000,
                                    progressBar: false,
                                    animation: {
                                        open: 'animated fadeInDown',
                                        close: 'animated fadeOutUp'
                                    }
                                }).show();
                                $('.refresh').prop("disabled", false);
                                return;
                            }
                        });
                    }
                <?php } ?>
                <?php if(in_array('add_admins', $c['admin']['groups'][$_SESSION['group']]) || in_array('remove_admins', $c['admin']['groups'][$_SESSION['group']])){?>
                    if(option == 'admins'){
                        $.ajax({
                            url: 'ajax.php',
                            type: 'POST',
                            data: {
                                type: 'admin-admins'
                            },
                            dataType: 'json',
                            success: function (data) {
                                //console.log(data);
                                if (data.type == 'error') {
                                    new Noty({
                                        layout: 'topRight',
                                        theme: 'metroui',
                                        type: data.type,
                                        text: data.message,
                                        timeout: 3000,
                                        progressBar: false,
                                        animation: {
                                            open: 'animated fadeInDown',
                                            close: 'animated fadeOutUp'
                                        }
                                    }).show();
                                    $('.refresh').prop("disabled", false);
                                    return;
                                }
                                $('#admins').DataTable().clear().draw();
                                try {
                                    $('#admins').DataTable().rows.add(data.list).draw();
                                } catch (e) {
                                    console.log(e);
                                }
                                if (noty == true) {
                                    new Noty({
                                        layout: 'topRight',
                                        theme: 'metroui',
                                        type: data.type,
                                        text: data.message,
                                        timeout: 5000,
                                        progressBar: false,
                                        animation: {
                                            open: 'animated fadeInDown',
                                            close: 'animated fadeOutUp'
                                        }
                                    }).show();
                                }
                                $('.refresh').prop("disabled", false);
                                return;
                            },
                            error: function (data) {
                                console.log(data);
                                new Noty({
                                    layout: 'topRight',
                                    theme: 'metroui',
                                    type: 'error',
                                    text: 'Wystąpił błąd (' + data + ')',
                                    timeout: 5000,
                                    progressBar: false,
                                    animation: {
                                        open: 'animated fadeInDown',
                                        close: 'animated fadeOutUp'
                                    }
                                }).show();
                                $('.refresh').prop("disabled", false);
                                return;
                            }
                        });
                    }
                <?php } ?>
            }
            function openwaiting(idd){
                $('.show-button').prop('disabled', true);
                $('.refresh').prop("disabled", true);
                $.ajax({
                    url: 'ajax.php',
                    type: 'POST',
                    data: {
                        type: 'load-waiting-application',
                        id: idd
                    },
                    dataType: 'json',
                    success: function(data){
                        //console.log(data);
                        if(data.type == 'error'){
                            new Noty({
                                layout: 'topRight',
                                theme: 'metroui',
                                type: data.type,
                                text: data.message,
                                timeout: 3000,
                                progressBar: false,
                                animation: {
                                    open: 'animated fadeInDown',
                                    close: 'animated fadeOutUp'
                                }
                            }).show();
                            $('.show-button').prop('disabled', false);
                            $('.refresh').prop("disabled", false);
                            return;
                        }
                        $('#waiting-modal #id').val(data.info.id);
                        $('#waiting-modal #title').html(`Podanie użytkownika ${data.info['discord_username']}#${data.info['discord_discriminator']}`);
                        $('#waiting-modal #info').html(`Data utworzenia: ${data.info['date-created']}`);
                        $('#waiting-modal .questions .question').remove();
                        data.questions.forEach(q => {
                            let section = $('<section class="question"></section>');
                            let title = $('<div class="title"></div>');
                            title.text(q['title']).appendTo(section);
                            let answer = $('<div class="answer"></div>');
                            answer.text(q['answer']).appendTo(section);
                            section.appendTo($('#waiting-modal .questions'));
                        });
                        $('#waiting-modal').modal('open');
                        $('.show-button').prop('disabled', false);
                        $('.refresh').prop("disabled", false);
                    },
                    error: function(data){
                        console.log(data);
                        new Noty({
                            layout: 'topRight',
                            theme: 'metroui',
                            type: 'error',
                            text: 'Wystąpił błąd (' + data + ')',
                            timeout: 5000,
                            progressBar: false,
                            animation: {
                                open: 'animated fadeInDown',
                                close: 'animated fadeOutUp'
                            }
                        }).show();
                        $('.show-button').prop('disabled', false);
                        $('.refresh').prop("disabled", false);
                        return;
                    }
                });
            }
            $('#waiting-modal #accept').click(function(){
                if($('#waiting-modal #id').val() == null){
                    return;
                }
                $('.show-button').prop('disabled', true);
                $('.refresh').prop("disabled", true);
                $('#waiting-modal').modal('close');
                var n = new Noty({
                    layout: 'center',
					theme: 'metroui',
                    type: 'information',
                    text: `Czy napewno chcesz zaakceptować podanie?<br/><b>(ID: ${$('#waiting-modal #id').val()})</b>`,
                    modal: true,
                    animation: {
                        open: 'animated bounceIn',
                        close: 'animated bounceOut'
                    },
                    buttons: [
                        Noty.button('Tak', 'btn btn-success margin-btn', function () {
                            n.close();
                            $.ajax({
                                url: 'ajax.php',
                                type: 'POST',
                                data: {
                                    type: 'accept-waiting-application',
                                    id: $('#waiting-modal #id').val()
                                },
                                dataType: 'json',
                                success: function(data){
                                    if(data.type == 'error'){
                                        new Noty({
                                            layout: 'topRight',
                                            theme: 'metroui',
                                            type: data.type,
                                            text: data.message,
                                            timeout: 500,
                                            progressBar: false,
                                            animation: {
                                                open: 'animated fadeInDown',
                                                close: 'animated fadeOutUp'
                                            }
                                        }).show();
                                        $('.show-button').prop('disabled', false);
                                        $('.refresh').prop("disabled", false);
                                        return;
                                    }
                                    refresh(false, 'all');
                                    $('.show-button').prop('disabled', false);
                                    $('.refresh').prop("disabled", false);
                                    new Noty({
                                        layout: 'topRight',
                                        theme: 'metroui',
                                        type: data.type,
                                        text: data.message,
                                        timeout: 500,
                                        progressBar: false,
                                        animation: {
                                            open: 'animated fadeInDown',
                                            close: 'animated fadeOutUp'
                                        }
                                    }).show();
                                    return;

                                },
                                error: function(data){
                                    console.log(data);
                                    new Noty({
                                        layout: 'topRight',
                                        theme: 'metroui',
                                        type: 'error',
                                        text: 'Wystąpił błąd (' + data + ')',
                                        timeout: 5000,
                                        progressBar: false,
                                        animation: {
                                            open: 'animated fadeInDown',
                                            close: 'animated fadeOutUp'
                                        }
                                    }).show();
                                    $('.refresh').prop("disabled", false);
                                    $('.show-button').prop('disabled', false);
                                    return;
                                }
                            });
                        }, {id: 'button1', 'data-status': 'ok'}),

                        Noty.button('Nie', 'btn btn-danger', function () {
                            n.close();
                            $('.show-button').prop('disabled', false);
                            $('.refresh').prop("disabled", false);
                            setTimeout(() => {
                                $('#waiting-modal').modal('open');
                            }, 1000);
                        })
                    ]
                }).show();
            });
            $('#waiting-modal #discard').click(function(){
                if($('#waiting-modal #id').val() == null){
                    return;
                }
                $('.show-button').prop('disabled', true);
                $('.refresh').prop("disabled", true);
                $('#waiting-modal').modal('close');
                var n = new Noty({
                    layout: 'center',
					theme: 'metroui',
                    type: 'information',
                    text: `Czy napewno chcesz odrzucić podanie?<br/><b>(ID: ${$('#waiting-modal #id').val()})</b>`,
                    modal: true,
                    animation: {
                        open: 'animated bounceIn',
                        close: 'animated bounceOut'
                    },
                    buttons: [
                        Noty.button('Tak', 'btn btn-success margin-btn', function () {
                            n.close();
                            $.ajax({
                                url: 'ajax.php',
                                type: 'POST',
                                data: {
                                    type: 'discard-waiting-application',
                                    id: $('#waiting-modal #id').val()
                                },
                                dataType: 'json',
                                success: function(data){
                                    //console.log(data);
                                    if(data.type == 'error'){
                                        new Noty({
                                            layout: 'topRight',
                                            theme: 'metroui',
                                            type: data.type,
                                            text: data.message,
                                            timeout: 3000,
                                            progressBar: false,
                                            animation: {
                                                open: 'animated fadeInDown',
                                                close: 'animated fadeOutUp'
                                            }
                                        }).show();
                                        $('.show-button').prop('disabled', false);
                                        $('.refresh').prop("disabled", false);
                                        return;
                                    }
                                    refresh(false, 'all');
                                    $('.show-button').prop('disabled', false);
                                    $('.refresh').prop("disabled", false);
                                    new Noty({
                                        layout: 'topRight',
                                        theme: 'metroui',
                                        type: data.type,
                                        text: data.message,
                                        timeout: 3000,
                                        progressBar: false,
                                        animation: {
                                            open: 'animated fadeInDown',
                                            close: 'animated fadeOutUp'
                                        }
                                    }).show();
                                    return;

                                },
                                error: function(data){
                                    console.log(data);
                                    new Noty({
                                        layout: 'topRight',
                                        theme: 'metroui',
                                        type: 'error',
                                        text: 'Wystąpił błąd (' + data + ')',
                                        timeout: 5000,
                                        progressBar: false,
                                        animation: {
                                            open: 'animated fadeInDown',
                                            close: 'animated fadeOutUp'
                                        }
                                    }).show();
                                    $('.show-button').prop('disabled', false);
                                    $('.refresh').prop("disabled", false);
                                    return;
                                }
                            });
                        }, {id: 'button1', 'data-status': 'ok'}),

                        Noty.button('Nie', 'btn btn-danger', function () {
                            n.close();
                            $('.show-button').prop('disabled', false);
                            $('.refresh').prop("disabled", false);
                            setTimeout(() => {
                                $('#waiting-modal').modal('open');
                            }, 1000);
                        })
                    ]
                }).show();
            });

            function showrecruitmentstage(idd){
                $('.show-button').prop('disabled', true);
                $('.refresh').prop("disabled", true);
                $.ajax({
                    url: 'ajax.php',
                    type: 'POST',
                    data: {
                        type: 'recruitmentstage-application',
                        id: idd
                    },
                    dataType: 'json',
                    success: function(data){
                        //console.log(data);
                        if(data.type == 'error'){
                            new Noty({
                                layout: 'topRight',
                                theme: 'metroui',
                                type: data.type,
                                text: data.message,
                                timeout: 3000,
                                progressBar: false,
                                animation: {
                                    open: 'animated fadeInDown',
                                    close: 'animated fadeOutUp'
                                }
                            }).show();
                            $('.show-button').prop('disabled', false);
                            $('.refresh').prop("disabled", false);
                            return;
                        }
                        $('#recruitmentstage-application #id').val(data.info.id);
                        $('#recruitmentstage-application #title').html(`Podanie użytkownika ${data.info['discord_username']}#${data.info['discord_discriminator']}`);
                        $('#recruitmentstage-application #info').html(`Data utworzenia: ${data.info['date-created']} | Data sprawdzenia: ${data.info['date-checked']} | Sprawdził/a: ${data.info['checked']}`);
                        $('#recruitmentstage-application .questions .question').remove();
                        data.questions.forEach(q => {
                            let section = $('<section class="question"></section>');
                            let title = $('<div class="title"></div>');
                            title.text(q['title']).appendTo(section);
                            let answer = $('<div class="answer"></div>');
                            answer.text(q['answer']).appendTo(section);
                            section.appendTo($('#recruitmentstage-application .questions'));
                        });
                        $('#recruitmentstage-application').modal('open');
                        $('.show-button').prop('disabled', false);
                        $('.refresh').prop("disabled", false);
                    },
                    error: function(data){
                        console.log(data);
                        $('.show-button').prop('disabled', false);
                        $('.refresh').prop("disabled", false);
                        new Noty({
                            layout: 'topRight',
                            theme: 'metroui',
                            type: 'error',
                            text: 'Wystąpił błąd (' + data + ')',
                            timeout: 5000,
                            progressBar: false,
                            animation: {
                                open: 'animated fadeInDown',
                                close: 'animated fadeOutUp'
                            }
                        }).show();
                        $('#refresh-waiting').prop("disabled", false);
                        $('.show-button').prop('disabled', false);
                        return;
                    }
                });
            }

            $('#recruitmentstage-application #accept').click(function(){
                if($('#recruitmentstage-application #id').val() == null){
                    return;
                }
                $('.show-button').prop('disabled', true);
                $('#recruitmentstage-application').modal('close');
                var n = new Noty({
                    layout: 'center',
					theme: 'metroui',
                    type: 'information',
                    text: `Czy napewno chcesz zdać rozmowę?<br/><b>(ID: ${$('#recruitmentstage-application #id').val()})</b>`,
                    modal: true,
                    animation: {
                        open: 'animated bounceIn',
                        close: 'animated bounceOut'
                    },
                    buttons: [
                        Noty.button('Tak', 'btn btn-success margin-btn', function () {
                            $('.refresh').prop("disabled", true);
                            n.close();
                            $.ajax({
                                url: 'ajax.php',
                                type: 'POST',
                                data: {
                                    type: 'accept-recruitmentstage-application',
                                    id: $('#recruitmentstage-application #id').val()
                                },
                                dataType: 'json',
                                success: function(data){
                                    if(data.type == 'error'){
                                        new Noty({
                                            layout: 'topRight',
                                            theme: 'metroui',
                                            type: data.type,
                                            text: data.message,
                                            timeout: 3000,
                                            progressBar: false,
                                            animation: {
                                                open: 'animated fadeInDown',
                                                close: 'animated fadeOutUp'
                                            }
                                        }).show();
                                        $('.show-button').prop('disabled', false);
                                        $('.refresh').prop("disabled", false);
                                        return;
                                    }
                                    refresh(false, 'all');
                                    $('.show-button').prop('disabled', false);
                                    $('.refresh').prop("disabled", false);
                                    new Noty({
                                        layout: 'topRight',
                                        theme: 'metroui',
                                        type: data.type,
                                        text: data.message,
                                        timeout: 3000,
                                        progressBar: false,
                                        animation: {
                                            open: 'animated fadeInDown',
                                            close: 'animated fadeOutUp'
                                        }
                                    }).show();
                                    return;

                                },
                                error: function(data){
                                    console.log(data);
                                    new Noty({
                                        layout: 'topRight',
                                        theme: 'metroui',
                                        type: 'error',
                                        text: 'Wystąpił błąd (' + data + ')',
                                        timeout: 5000,
                                        progressBar: false,
                                        animation: {
                                            open: 'animated fadeInDown',
                                            close: 'animated fadeOutUp'
                                        }
                                    }).show();
                                    $('.refresh').prop("disabled", false);
                                    $('.show-button').prop('disabled', false);
                                    return;
                                }
                            });
                        }, {id: 'button1', 'data-status': 'ok'}),

                        Noty.button('Nie', 'btn btn-danger', function () {
                            n.close();
                            $('.show-button').prop('disabled', false);
                            $('.refresh').prop("disabled", false);
                            setTimeout(() => {
                                $('#recruitmentstage-application').modal('open');
                            }, 1000);
                        })
                    ]
                }).show();
            });

            $('#recruitmentstage-application #discard').click(function(){
                if($('#recruitmentstage-application #id').val() == null){
                    return;
                }
                $('.show-button').prop('disabled', true);
                $('#recruitmentstage-application').modal('close');
                var n = new Noty({
                    layout: 'center',
					theme: 'metroui',
                    type: 'information',
                    text: `Czy napewno chcesz niezdać rozmowy?<br/><b>(ID: ${$('#recruitmentstage-application #id').val()})</b>`,
                    modal: true,
                    animation: {
                        open: 'animated bounceIn',
                        close: 'animated bounceOut'
                    },
                    buttons: [
                        Noty.button('Tak', 'btn btn-success margin-btn', function () {
                            $('.refresh').prop("disabled", true);
                            n.close();
                            $.ajax({
                                url: 'ajax.php',
                                type: 'POST',
                                data: {
                                    type: 'discard-recruitmentstage-application',
                                    id: $('#recruitmentstage-application #id').val()
                                },
                                dataType: 'json',
                                success: function(data){
                                    if(data.type == 'error'){
                                        new Noty({
                                            layout: 'topRight',
                                            theme: 'metroui',
                                            type: data.type,
                                            text: data.message,
                                            timeout: 3000,
                                            progressBar: false,
                                            animation: {
                                                open: 'animated fadeInDown',
                                                close: 'animated fadeOutUp'
                                            }
                                        }).show();
                                        $('.show-button').prop('disabled', false);
                                        $('.refresh').prop("disabled", false);
                                        return;
                                    }
                                    refresh(false, 'all');
                                    $('.show-button').prop('disabled', false);
                                    $('.refresh').prop("disabled", false);
                                    new Noty({
                                        layout: 'topRight',
                                        theme: 'metroui',
                                        type: data.type,
                                        text: data.message,
                                        timeout: 3000,
                                        progressBar: false,
                                        animation: {
                                            open: 'animated fadeInDown',
                                            close: 'animated fadeOutUp'
                                        }
                                    }).show();
                                    return;

                                },
                                error: function(data){
                                    console.log(data);
                                    $('.show-button').prop('disabled', false);
                                    $('.refresh').prop("disabled", false);
                                    new Noty({
                                        layout: 'topRight',
                                        theme: 'metroui',
                                        type: 'error',
                                        text: 'Wystąpił błąd (' + data + ')',
                                        timeout: 5000,
                                        progressBar: false,
                                        animation: {
                                            open: 'animated fadeInDown',
                                            close: 'animated fadeOutUp'
                                        }
                                    }).show();
                                    $('#refresh-waiting').prop("disabled", false);
                                    $('#show-button').prop('disabled', false);
                                    return;
                                }
                            });
                        }, {id: 'button1', 'data-status': 'ok'}),

                        Noty.button('Nie', 'btn btn-danger', function () {
                            n.close();
                            $('.show-button').prop('disabled', false);
                            $('.refresh').prop("disabled", false);
                            setTimeout(() => {
                                $('#recruitmentstage-application').modal('open');
                            }, 1000);
                        })
                    ]
                }).show();
            });
            function showapplication(idd){
                $('.show-button').prop('disabled', true);
                $.ajax({
                    url: 'ajax.php',
                    type: 'POST',
                    data: {
                        type: 'show-application',
                        id: idd
                    },
                    dataType: 'json',
                    success: function(data){
                        if(data.type == 'error'){
                            new Noty({
                                layout: 'topRight',
                                theme: 'metroui',
                                type: data.type,
                                text: data.message,
                                timeout: 3000,
                                progressBar: false,
                                animation: {
                                    open: 'animated fadeInDown',
                                    close: 'animated fadeOutUp'
                                }
                            }).show();
                            $('.show-button').prop('disabled', false);
                            return;
                        }
                        //console.log(data);
                        $('#show-application #title').html(`Podanie użytkownika ${data.info['discord_username']}#${data.info['discord_discriminator']}`);
                        $('#show-application #info').html('Data utworzenia: '+data.info['date-created']+'r. | Data sprawdzenia: '+data.info['date-checked']+' | Rozpatrzone przez '+data.info['checked'] + ((data.info.conductedconversation != null) ? '<br/>Rozmowa odbyła się: '+data.info['date-conductedconversation']+' | Przeprowadzona przez: '+data.info.conductedconversation : ''));
                        $('#show-application #id').val(data.info['id']);
                        $('#show-application .questions .question').remove();
                        data.questions.forEach(q => {
                            let section = $('<section class="question"></section>');
                            let title = $('<div class="title"></div>');
                            title.text(q['title']).appendTo(section);
                            let answer = $('<div class="answer"></div>');
                            answer.text(q['answer']).appendTo(section);
                            section.appendTo($('#show-application .questions'));
                        });
                        $('#show-application').modal('open');
                        $('.show-button').prop('disabled', false);
                    },
                    error: function(data){
                        console.log(data);
                        $('.show-button').prop('disabled', false);
                        new Noty({
                            layout: 'topRight',
                            theme: 'metroui',
                            type: 'error',
                            text: 'Wystąpił błąd (' + data + ')',
                            timeout: 5000,
                            progressBar: false,
                            animation: {
                                open: 'animated fadeInDown',
                                close: 'animated fadeOutUp'
                            }
                        }).show();
                        $('#refresh-waiting').prop("disabled", false);
                        $('#show-button').prop('disabled', false);
                        return;
                    }
                });
            }
            <?php if(in_array('remove_app', $c['admin']['groups'][$_SESSION['group']])){?>
                $('#waiting-modal #deleteapp').click(() => {
                    var n = new Noty({
                        layout: 'center',
                        theme: 'metroui',
                        type: 'information',
                        text: `Czy napewno usunąć aplikację?<br/><b>(ID: ${$('#waiting-modal #id').val()})</b>`,
                        modal: true,
                        animation: {
                            open: 'animated bounceIn',
                            close: 'animated bounceOut'
                        },
                        buttons: [
                            Noty.button('Tak', 'btn btn-success margin-btn', function () {
                                $('.refresh').prop("disabled", true);
                                n.close();
                                $.ajax({
                                    url: 'ajax.php',
                                    type: 'POST',
                                    data: {
                                        type: 'delete-application',
                                        id: $('#waiting-modal #id').val()
                                    },
                                    dataType: 'json',
                                    success: function(data){
                                        if(data.type == 'error'){
                                            new Noty({
                                                layout: 'topRight',
                                                theme: 'metroui',
                                                type: data.type,
                                                text: data.message,
                                                timeout: 3000,
                                                progressBar: false,
                                                animation: {
                                                    open: 'animated fadeInDown',
                                                    close: 'animated fadeOutUp'
                                                }
                                            }).show();
                                            $('.show-button').prop('disabled', false);
                                            $('.refresh').prop("disabled", false);
                                            return;
                                        }
                                        refresh(false, 'all');
                                        $('.show-button').prop('disabled', false);
                                        $('.refresh').prop("disabled", false);
                                        new Noty({
                                            layout: 'topRight',
                                            theme: 'metroui',
                                            type: data.type,
                                            text: data.message,
                                            timeout: 3000,
                                            progressBar: false,
                                            animation: {
                                                open: 'animated fadeInDown',
                                                close: 'animated fadeOutUp'
                                            }
                                        }).show();
                                        return;

                                    },
                                    error: function(data){
                                        console.log(data);
                                        $('.show-button').prop('disabled', false);
                                        $('.refresh').prop("disabled", false);
                                        new Noty({
                                            layout: 'topRight',
                                            theme: 'metroui',
                                            type: 'error',
                                            text: 'Wystąpił błąd (' + data + ')',
                                            timeout: 5000,
                                            progressBar: false,
                                            animation: {
                                                open: 'animated fadeInDown',
                                                close: 'animated fadeOutUp'
                                            }
                                        }).show();
                                        $('#refresh-waiting').prop("disabled", false);
                                        $('#show-button').prop('disabled', false);
                                        return;
                                    }
                                });
                            }, {id: 'button1', 'data-status': 'ok'}),

                            Noty.button('Nie', 'btn btn-danger', function () {
                                n.close();
                                $('.show-button').prop('disabled', false);
                                $('.refresh').prop("disabled", false);
                                setTimeout(() => {
                                    $('#waiting-modal').modal('open');
                                }, 1000);
                            })
                        ]
                    }).show();
                });
                $('#show-application #deleteapp').click(() => {
                    var n = new Noty({
                        layout: 'center',
                        theme: 'metroui',
                        type: 'information',
                        text: `Czy napewno usunąć aplikację?<br/><b>(ID: ${$('#show-application #id').val()})</b>`,
                        modal: true,
                        animation: {
                            open: 'animated bounceIn',
                            close: 'animated bounceOut'
                        },
                        buttons: [
                            Noty.button('Tak', 'btn btn-success margin-btn', function () {
                                $('.refresh').prop("disabled", true);
                                n.close();
                                $.ajax({
                                    url: 'ajax.php',
                                    type: 'POST',
                                    data: {
                                        type: 'delete-application',
                                        id: $('#show-application #id').val()
                                    },
                                    dataType: 'json',
                                    success: function(data){
                                        if(data.type == 'error'){
                                            new Noty({
                                                layout: 'topRight',
                                                theme: 'metroui',
                                                type: data.type,
                                                text: data.message,
                                                timeout: 3000,
                                                progressBar: false,
                                                animation: {
                                                    open: 'animated fadeInDown',
                                                    close: 'animated fadeOutUp'
                                                }
                                            }).show();
                                            $('.show-button').prop('disabled', false);
                                            $('.refresh').prop("disabled", false);
                                            return;
                                        }
                                        refresh(false, 'all');
                                        $('.show-button').prop('disabled', false);
                                        $('.refresh').prop("disabled", false);
                                        new Noty({
                                            layout: 'topRight',
                                            theme: 'metroui',
                                            type: data.type,
                                            text: data.message,
                                            timeout: 3000,
                                            progressBar: false,
                                            animation: {
                                                open: 'animated fadeInDown',
                                                close: 'animated fadeOutUp'
                                            }
                                        }).show();
                                        return;

                                    },
                                    error: function(data){
                                        console.log(data);
                                        $('.show-button').prop('disabled', false);
                                        $('.refresh').prop("disabled", false);
                                        new Noty({
                                            layout: 'topRight',
                                            theme: 'metroui',
                                            type: 'error',
                                            text: 'Wystąpił błąd (' + data + ')',
                                            timeout: 5000,
                                            progressBar: false,
                                            animation: {
                                                open: 'animated fadeInDown',
                                                close: 'animated fadeOutUp'
                                            }
                                        }).show();
                                        $('#refresh-waiting').prop("disabled", false);
                                        $('#show-button').prop('disabled', false);
                                        return;
                                    }
                                });
                            }, {id: 'button1', 'data-status': 'ok'}),

                            Noty.button('Nie', 'btn btn-danger', function () {
                                n.close();
                                $('.show-button').prop('disabled', false);
                                $('.refresh').prop("disabled", false);
                                setTimeout(() => {
                                    $('#show-application').modal('open');
                                }, 1000);
                            })
                        ]
                    }).show();
                });
                $('#recruitmentstage-application #deleteapp').click(() => {
                    var n = new Noty({
                        layout: 'center',
                        theme: 'metroui',
                        type: 'information',
                        text: `Czy napewno usunąć aplikację?<br/><b>(ID: ${$('#recruitmentstage-application #id').val()})</b>`,
                        modal: true,
                        animation: {
                            open: 'animated bounceIn',
                            close: 'animated bounceOut'
                        },
                        buttons: [
                            Noty.button('Tak', 'btn btn-success margin-btn', function () {
                                $('.refresh').prop("disabled", true);
                                n.close();
                                $.ajax({
                                    url: 'ajax.php',
                                    type: 'POST',
                                    data: {
                                        type: 'delete-application',
                                        id: $('#recruitmentstage-application #id').val()
                                    },
                                    dataType: 'json',
                                    success: function(data){
                                        if(data.type == 'error'){
                                            new Noty({
                                                layout: 'topRight',
                                                theme: 'metroui',
                                                type: data.type,
                                                text: data.message,
                                                timeout: 3000,
                                                progressBar: false,
                                                animation: {
                                                    open: 'animated fadeInDown',
                                                    close: 'animated fadeOutUp'
                                                }
                                            }).show();
                                            $('.show-button').prop('disabled', false);
                                            $('.refresh').prop("disabled", false);
                                            return;
                                        }
                                        refresh(false, 'all');
                                        $('.show-button').prop('disabled', false);
                                        $('.refresh').prop("disabled", false);
                                        new Noty({
                                            layout: 'topRight',
                                            theme: 'metroui',
                                            type: data.type,
                                            text: data.message,
                                            timeout: 3000,
                                            progressBar: false,
                                            animation: {
                                                open: 'animated fadeInDown',
                                                close: 'animated fadeOutUp'
                                            }
                                        }).show();
                                        return;

                                    },
                                    error: function(data){
                                        console.log(data);
                                        $('.show-button').prop('disabled', false);
                                        $('.refresh').prop("disabled", false);
                                        new Noty({
                                            layout: 'topRight',
                                            theme: 'metroui',
                                            type: 'error',
                                            text: 'Wystąpił błąd (' + data + ')',
                                            timeout: 5000,
                                            progressBar: false,
                                            animation: {
                                                open: 'animated fadeInDown',
                                                close: 'animated fadeOutUp'
                                            }
                                        }).show();
                                        $('#refresh-waiting').prop("disabled", false);
                                        $('#show-button').prop('disabled', false);
                                        return;
                                    }
                                });
                            }, {id: 'button1', 'data-status': 'ok'}),

                            Noty.button('Nie', 'btn btn-danger', function () {
                                n.close();
                                $('.show-button').prop('disabled', false);
                                $('.refresh').prop("disabled", false);
                                setTimeout(() => {
                                    $('#recruitmentstage-application').modal('open');
                                }, 1000);
                            })
                        ]
                    }).show();
                });
            <?php } ?>
            <?php if(in_array('add_admins', $c['admin']['groups'][$_SESSION['group']]) || in_array('remove_admins', $c['admin']['groups'][$_SESSION['group']])){?>
                function openmanageadmins(){
                    $('#manage-admins').modal('open');
                    refresh(false, 'admins')
                } // add-admin

                $('#manage-admins #add-admin').click(function(){
                    if($('#manage-admins #name').val() == '' || $('#manage-admins #discord-id').val() == ''){
                        new Noty({
                            layout: 'topRight',
                            theme: 'metroui',
                            type: 'error',
                            text: 'Wypełnij wszystkie pola',
                            timeout: 3000,
                            progressBar: false,
                            animation: {
                                open: 'animated fadeInDown',
                                close: 'animated fadeOutUp'
                            }
                        }).show();
                        return;
                    }
                    $('.show-button').prop('disabled', true);
                    $('.refresh').prop("disabled", true);
                    $.ajax({
                        url: 'ajax.php',
                        type: 'POST',
                        data: {
                            type: 'add-admin',
                            name: $('#manage-admins #name').val(),
                            discordid: $('#manage-admins #discord-id').val(),
                            group: $('#manage-admins #group').val(),
                        },
                        dataType: 'json',
                        success: function (data) {
                            if(data.type == 'error'){
                                new Noty({
                                    layout: 'topRight',
                                    theme: 'metroui',
                                    type: data.type,
                                    text: data.message,
                                    timeout: 3000,
                                    progressBar: false,
                                    animation: {
                                        open: 'animated fadeInDown',
                                        close: 'animated fadeOutUp'
                                    }
                                }).show();
                                $('.show-button').prop('disabled', false);
                                $('.refresh').prop("disabled", false);
                                return;
                            }
                            refresh(false, 'admins');
                            $('#manage-admins #name').val(''),
                            $('#manage-admins #discord-id').val(''),
                            $('.show-button').prop('disabled', false);
                            $('.refresh').prop("disabled", false);
                            new Noty({
                                layout: 'topRight',
                                theme: 'metroui',
                                type: data.type,
                                text: data.message,
                                timeout: 3000,
                                progressBar: false,
                                animation: {
                                    open: 'animated fadeInDown',
                                    close: 'animated fadeOutUp'
                                }
                            }).show();
                            return;

                        },
                        error: function (data) {
                            console.log(data);
                            new Noty({
                                layout: 'topRight',
                                theme: 'metroui',
                                type: 'error',
                                text: 'Wystąpił błąd (' + data + ')',
                                timeout: 5000,
                                progressBar: false,
                                animation: {
                                    open: 'animated fadeInDown',
                                    close: 'animated fadeOutUp'
                                }
                            }).show();
                            $('.refresh').prop("disabled", false);
                            $('.show-button').prop('disabled', false);
                            return;
                        }
                    });
                });
                function deleteadmin(id){
                    if($('#waiting-modal #id').val() == null){
                        return;
                    }
                    $('.show-button').prop('disabled', true);
                    $('.refresh').prop("disabled", true);
                    $('#manage-admins').modal('close');
                    var n = new Noty({
                        layout: 'center',
                        theme: 'metroui',
                        type: 'information',
                        text: `Czy napewno chcesz usunąć admina?<br/><b>(ID: ${id})</b>`,
                        modal: true,
                        animation: {
                            open: 'animated bounceIn',
                            close: 'animated bounceOut'
                        },
                        buttons: [
                            Noty.button('Tak', 'btn btn-success margin-btn', function () {
                                n.close();
                                $.ajax({
                                    url: 'ajax.php',
                                    type: 'POST',
                                    data: {
                                        type: 'delete-admin',
                                        id: id
                                    },
                                    dataType: 'json',
                                    success: function(data){
                                        //console.log(data);
                                        if(data.type == 'error'){
                                            new Noty({
                                                layout: 'topRight',
                                                theme: 'metroui',
                                                type: data.type,
                                                text: data.message,
                                                timeout: 3000,
                                                progressBar: false,
                                                animation: {
                                                    open: 'animated fadeInDown',
                                                    close: 'animated fadeOutUp'
                                                }
                                            }).show();
                                            $('.show-button').prop('disabled', false);
                                            $('.refresh').prop("disabled", false);
                                            return;
                                        }
                                        refresh(false, 'admins');
                                        $('.show-button').prop('disabled', false);
                                        $('.refresh').prop("disabled", false);
                                        setTimeout(() => {
                                            $('#manage-admins').modal('open');
                                        }, 1000);
                                        new Noty({
                                            layout: 'topRight',
                                            theme: 'metroui',
                                            type: data.type,
                                            text: data.message,
                                            timeout: 3000,
                                            progressBar: false,
                                            animation: {
                                                open: 'animated fadeInDown',
                                                close: 'animated fadeOutUp'
                                            }
                                        }).show();
                                        return;

                                    },
                                    error: function(data){
                                        console.log(data);
                                        $('.show-button').prop('disabled', false);
                                        $('.refresh').prop("disabled", false);
                                        setTimeout(() => {
                                            $('#manage-admins').modal('open');
                                        }, 1000);
                                        new Noty({
                                            layout: 'topRight',
                                            theme: 'metroui',
                                            type: 'error',
                                            text: 'Wystąpił błąd (' + data + ')',
                                            timeout: 5000,
                                            progressBar: false,
                                            animation: {
                                                open: 'animated fadeInDown',
                                                close: 'animated fadeOutUp'
                                            }
                                        }).show();
                                        $('.show-button').prop('disabled', false);
                                        $('.refresh').prop("disabled", false);
                                        return;
                                    }
                                });
                            }, {id: 'button1', 'data-status': 'ok'}),

                            Noty.button('Nie', 'btn btn-danger', function () {
                                n.close();
                                $('.show-button').prop('disabled', false);
                                $('.refresh').prop("disabled", false);
                                setTimeout(() => {
                                    $('#manage-admins').modal('open');
                                }, 1000);
                            })
                        ]
                    }).show();
                }
            <?php } ?>

            <?php if(in_array('remove_from_block', $c['admin']['groups'][$_SESSION['group']])){?>
                $('#waiting-modal #addblock').click(() => {
                    var n = new Noty({
                        layout: 'center',
                        theme: 'metroui',
                        type: 'information',
                        text: `Czy napewno chcesz dodać do zablokowanych?<br/><b>(ID: ${$('#waiting-modal #id').val()})</b>`,
                        modal: true,
                        animation: {
                            open: 'animated bounceIn',
                            close: 'animated bounceOut'
                        },
                        buttons: [
                            Noty.button('Tak', 'btn btn-success margin-btn', function () {
                                $('.refresh').prop("disabled", true);
                                n.close();
                                $.ajax({
                                    url: 'ajax.php',
                                    type: 'POST',
                                    data: {
                                        type: 'add-to-block',
                                        id: $('#waiting-modal #id').val()
                                    },
                                    dataType: 'json',
                                    success: function(data){
                                        if(data.type == 'error'){
                                            new Noty({
                                                layout: 'topRight',
                                                theme: 'metroui',
                                                type: data.type,
                                                text: data.message,
                                                timeout: 3000,
                                                progressBar: false,
                                                animation: {
                                                    open: 'animated fadeInDown',
                                                    close: 'animated fadeOutUp'
                                                }
                                            }).show();
                                            $('.show-button').prop('disabled', false);
                                            $('.refresh').prop("disabled", false);
                                            return;
                                        }
                                        refresh(false, 'all');
                                        $('.show-button').prop('disabled', false);
                                        $('.refresh').prop("disabled", false);
                                        new Noty({
                                            layout: 'topRight',
                                            theme: 'metroui',
                                            type: data.type,
                                            text: data.message,
                                            timeout: 3000,
                                            progressBar: false,
                                            animation: {
                                                open: 'animated fadeInDown',
                                                close: 'animated fadeOutUp'
                                            }
                                        }).show();
                                        return;

                                    },
                                    error: function(data){
                                        console.log(data);
                                        $('.show-button').prop('disabled', false);
                                        $('.refresh').prop("disabled", false);
                                        new Noty({
                                            layout: 'topRight',
                                            theme: 'metroui',
                                            type: 'error',
                                            text: 'Wystąpił błąd (' + data + ')',
                                            timeout: 5000,
                                            progressBar: false,
                                            animation: {
                                                open: 'animated fadeInDown',
                                                close: 'animated fadeOutUp'
                                            }
                                        }).show();
                                        $('#refresh-waiting').prop("disabled", false);
                                        $('#show-button').prop('disabled', false);
                                        return;
                                    }
                                });
                            }, {id: 'button1', 'data-status': 'ok'}),

                            Noty.button('Nie', 'btn btn-danger', function () {
                                n.close();
                                $('.show-button').prop('disabled', false);
                                $('.refresh').prop("disabled", false);
                                setTimeout(() => {
                                    $('#waiting-modal').modal('open');
                                }, 1000);
                            })
                        ]
                    }).show();
                });
                $('#show-application #addblock').click(() => {
                    var n = new Noty({
                        layout: 'center',
                        theme: 'metroui',
                        type: 'information',
                        text: `Czy napewno chcesz dodać do zablokowanych?<br/><b>(ID: ${$('#show-application #id').val()})</b>`,
                        modal: true,
                        animation: {
                            open: 'animated bounceIn',
                            close: 'animated bounceOut'
                        },
                        buttons: [
                            Noty.button('Tak', 'btn btn-success margin-btn', function () {
                                $('.refresh').prop("disabled", true);
                                n.close();
                                $.ajax({
                                    url: 'ajax.php',
                                    type: 'POST',
                                    data: {
                                        type: 'add-to-block',
                                        id: $('#show-application #id').val()
                                    },
                                    dataType: 'json',
                                    success: function(data){
                                        if(data.type == 'error'){
                                            new Noty({
                                                layout: 'topRight',
                                                theme: 'metroui',
                                                type: data.type,
                                                text: data.message,
                                                timeout: 3000,
                                                progressBar: false,
                                                animation: {
                                                    open: 'animated fadeInDown',
                                                    close: 'animated fadeOutUp'
                                                }
                                            }).show();
                                            $('.show-button').prop('disabled', false);
                                            $('.refresh').prop("disabled", false);
                                            return;
                                        }
                                        refresh(false, 'all');
                                        $('.show-button').prop('disabled', false);
                                        $('.refresh').prop("disabled", false);
                                        new Noty({
                                            layout: 'topRight',
                                            theme: 'metroui',
                                            type: data.type,
                                            text: data.message,
                                            timeout: 3000,
                                            progressBar: false,
                                            animation: {
                                                open: 'animated fadeInDown',
                                                close: 'animated fadeOutUp'
                                            }
                                        }).show();
                                        return;

                                    },
                                    error: function(data){
                                        console.log(data);
                                        $('.show-button').prop('disabled', false);
                                        $('.refresh').prop("disabled", false);
                                        new Noty({
                                            layout: 'topRight',
                                            theme: 'metroui',
                                            type: 'error',
                                            text: 'Wystąpił błąd (' + data + ')',
                                            timeout: 5000,
                                            progressBar: false,
                                            animation: {
                                                open: 'animated fadeInDown',
                                                close: 'animated fadeOutUp'
                                            }
                                        }).show();
                                        $('#refresh-waiting').prop("disabled", false);
                                        $('#show-button').prop('disabled', false);
                                        return;
                                    }
                                });
                            }, {id: 'button1', 'data-status': 'ok'}),

                            Noty.button('Nie', 'btn btn-danger', function () {
                                n.close();
                                $('.show-button').prop('disabled', false);
                                $('.refresh').prop("disabled", false);
                                setTimeout(() => {
                                    $('#show-application').modal('open');
                                }, 1000);
                            })
                        ]
                    }).show();
                });
                $('#recruitmentstage-application #addblock').click(() => {
                    var n = new Noty({
                        layout: 'center',
                        theme: 'metroui',
                        type: 'information',
                        text: `Czy napewno chcesz dodać do zablokowanych?<br/><b>(ID: ${$('#recruitmentstage-application #id').val()})</b>`,
                        modal: true,
                        animation: {
                            open: 'animated bounceIn',
                            close: 'animated bounceOut'
                        },
                        buttons: [
                            Noty.button('Tak', 'btn btn-success margin-btn', function () {
                                $('.refresh').prop("disabled", true);
                                n.close();
                                $.ajax({
                                    url: 'ajax.php',
                                    type: 'POST',
                                    data: {
                                        type: 'add-to-block',
                                        id: $('#recruitmentstage-application #id').val()
                                    },
                                    dataType: 'json',
                                    success: function(data){
                                        if(data.type == 'error'){
                                            new Noty({
                                                layout: 'topRight',
                                                theme: 'metroui',
                                                type: data.type,
                                                text: data.message,
                                                timeout: 3000,
                                                progressBar: false,
                                                animation: {
                                                    open: 'animated fadeInDown',
                                                    close: 'animated fadeOutUp'
                                                }
                                            }).show();
                                            $('.show-button').prop('disabled', false);
                                            $('.refresh').prop("disabled", false);
                                            return;
                                        }
                                        refresh(false, 'all');
                                        $('.show-button').prop('disabled', false);
                                        $('.refresh').prop("disabled", false);
                                        new Noty({
                                            layout: 'topRight',
                                            theme: 'metroui',
                                            type: data.type,
                                            text: data.message,
                                            timeout: 3000,
                                            progressBar: false,
                                            animation: {
                                                open: 'animated fadeInDown',
                                                close: 'animated fadeOutUp'
                                            }
                                        }).show();
                                        return;

                                    },
                                    error: function(data){
                                        console.log(data);
                                        $('.show-button').prop('disabled', false);
                                        $('.refresh').prop("disabled", false);
                                        new Noty({
                                            layout: 'topRight',
                                            theme: 'metroui',
                                            type: 'error',
                                            text: 'Wystąpił błąd (' + data + ')',
                                            timeout: 5000,
                                            progressBar: false,
                                            animation: {
                                                open: 'animated fadeInDown',
                                                close: 'animated fadeOutUp'
                                            }
                                        }).show();
                                        $('#refresh-waiting').prop("disabled", false);
                                        $('#show-button').prop('disabled', false);
                                        return;
                                    }
                                });
                            }, {id: 'button1', 'data-status': 'ok'}),

                            Noty.button('Nie', 'btn btn-danger', function () {
                                n.close();
                                $('.show-button').prop('disabled', false);
                                $('.refresh').prop("disabled", false);
                                setTimeout(() => {
                                    $('#recruitmentstage-application').modal('open');
                                }, 1000);
                            })
                        ]
                    }).show();
                });
            <?php } ?>

            <?php if(in_array('remove_from_block', $c['admin']['groups'][$_SESSION['group']])){?>
                function deleteblocked(id){
                    var n = new Noty({
                        layout: 'center',
                        theme: 'metroui',
                        type: 'information',
                        text: `Czy napewno chcesz usunąć zablokowanego?<br/><b>(ID: ${id})</b>`,
                        modal: true,
                        animation: {
                            open: 'animated bounceIn',
                            close: 'animated bounceOut'
                        },
                        buttons: [
                            Noty.button('Tak', 'btn btn-success margin-btn', function () {
                                n.close();
                                $.ajax({
                                    url: 'ajax.php',
                                    type: 'POST',
                                    data: {
                                        type: 'remove-from-block',
                                        id: id
                                    },
                                    dataType: 'json',
                                    success: function(data){
                                        //console.log(data);
                                        if(data.type == 'error'){
                                            new Noty({
                                                layout: 'topRight',
                                                theme: 'metroui',
                                                type: data.type,
                                                text: data.message,
                                                timeout: 3000,
                                                progressBar: false,
                                                animation: {
                                                    open: 'animated fadeInDown',
                                                    close: 'animated fadeOutUp'
                                                }
                                            }).show();
                                            $('.show-button').prop('disabled', false);
                                            $('.refresh').prop("disabled", false);
                                            return;
                                        }
                                        refresh(false, 'blocked');
                                        $('.show-button').prop('disabled', false);
                                        $('.refresh').prop("disabled", false);
                                        new Noty({
                                            layout: 'topRight',
                                            theme: 'metroui',
                                            type: data.type,
                                            text: data.message,
                                            timeout: 3000,
                                            progressBar: false,
                                            animation: {
                                                open: 'animated fadeInDown',
                                                close: 'animated fadeOutUp'
                                            }
                                        }).show();
                                        return;

                                    },
                                    error: function(data){
                                        console.log(data);
                                        $('.show-button').prop('disabled', false);
                                        $('.refresh').prop("disabled", false);
                                        new Noty({
                                            layout: 'topRight',
                                            theme: 'metroui',
                                            type: 'error',
                                            text: 'Wystąpił błąd (' + data + ')',
                                            timeout: 5000,
                                            progressBar: false,
                                            animation: {
                                                open: 'animated fadeInDown',
                                                close: 'animated fadeOutUp'
                                            }
                                        }).show();
                                        $('.show-button').prop('disabled', false);
                                        $('.refresh').prop("disabled", false);
                                        return;
                                    }
                                });
                            }, {id: 'button1', 'data-status': 'ok'}),

                            Noty.button('Nie', 'btn btn-danger', function () {
                                n.close();
                                $('.show-button').prop('disabled', false);
                                $('.refresh').prop("disabled", false);
                            })
                        ]
                    }).show();
                }
            <?php } ?>
        </script>
    </body>
</html>