<?php

ini_set('session.gc_maxlifetime', 2*60*60); // 2 hours
session_start();
require_once 'vendor/autoload.php';
require_once 'cfg.php';

$provider = new \Discord\OAuth\Discord([
    'clientId' => $c['oauth2']['client_id'],
    'clientSecret' => $c['oauth2']['client_secret'],
    'redirectUri' => $c['oauth2']['redirectUri_index']
]);

if(isset($_GET['login'])){
	header('Location: '.$provider->getAuthorizationUrl(['scope' => ['identify', 'email', 'guilds.join']]));
	exit();
}

if(isset($_GET['logout'])){
    if(isset($_SESSION['islogged'])){
        session_destroy();
        session_unset();
    }
    header('Location: index.php');
    exit();
}

if(isset($_GET['error'])){
    if($_GET['error'] == 'access_denied'){
        $_SESSION['error'] = 'Właściciel zasobu lub serwer autoryzacyjny odmówił żądania. Spróbuj zalogować się jeszcze raz.';
        header('Location: index.php');
        exit();
    }
}

if(isset($_GET['code'])){
	$sql = @new mysqli($c['mysql']['ip'], $c['mysql']['user'], $c['mysql']['password'], $c['mysql']['database']);
	if($sql->connect_errno){
        $_SESSION['error'] = 'Występił błąd z połączeniem się do bazy danych';
		header('Location: index.php');
		$sql->close();
		exit();
    }
	$token = $provider->getAccessToken('authorization_code', [
		'code' => $_GET['code'],
    ]);

    $user = $provider->getResourceOwner($token);
    $zap = $sql->query('SELECT * FROM `blocked` WHERE `discord_id`="'.$user->id.'";');
    if($zap->num_rows > 0){
        $sql->close();
        $_SESSION['error'] = 'Twoje konto zostało zablokowane przez administartora, jeżeli niesłusznie, zgłoś się do administratora na discordzie.';
        header('Location: index.php');
        exit();
    }
	$sql->close();
	if($user->verified == 1){
		$_SESSION['islogged'] = true;
		$_SESSION['id'] = $user->id;
		$_SESSION['username'] = $user->username;
		$_SESSION['email'] = $user->email;
		$_SESSION['discriminator'] = $user->discriminator;
		$_SESSION['avatar'] = $user->avatar;
		$_SESSION['verified'] = $user->verified;
        $_SESSION['mfa_enabled'] = $user->mfa_enabled;
        $inv = $user->acceptInvite($c['oauth2']['inviteLink']);
        header('Location: index.php');
        exit();
	} else{
        $_SESSION['error'] = 'Twoje konto <b>Discord</b> musi być zweryfikowane za pomocą e-maila.';
		header('Location: index.php');
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
        <meta name="keywords" content="<?= $c['keywords'] ?>">
        <meta name="description" content="<?= $c['description'] ?>">

        <title>Aplikacja <?= $c['surfix'] ?></title>
        <link rel="icon" type="image/ico" href="img/logoglowna.png" />
        <link href="https://fonts.googleapis.com/css?family=Roboto:400,700&amp;subset=latin-ext" rel="stylesheet">
        <link rel="stylesheet" href="./assets/css/loader.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/datepicker/0.6.5/datepicker.min.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/3.7.0/animate.css">
        <link rel="stylesheet" href="./assets/css/app.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/noty/3.1.4/noty.css">
        <link rel="stylesheet" href="./assets/css/themes/metroui.css">
        <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.2/css/all.css"
        integrity="sha384-fnmOCqbTlWIlj8LyTjo7mOUStjsKC4pOpQbqyi7RrhN7udi9RwhKkMHpvLbHG9Sr" crossorigin="anonymous">
    </head>
    <body>
        <noscript>Jeżeli chcesz używać tej strony musisz mieć włączonego JavaScripta</noscript>
        <div id="loader" class="loader">
			<div class="lds-ring"><div></div><div></div><div></div><div></div></div>
        </div>
        <?php
        if(isset($_SESSION['islogged'])){
            $sql = @new mysqli($c['mysql']['ip'], $c['mysql']['user'], $c['mysql']['password'], $c['mysql']['database']);
            if($sql->connect_errno){
                header('Location: index.php');
                $sql->close();
                exit();
            }
            ?>

            <div class="title">
                <?=$c['name']?><br>
                <div id="status"></div>
                <img class="logoMain" src="https://cdn.discordapp.com/avatars/<?=$_SESSION['id']?>/<?=$_SESSION['avatar']?>.png" alt="">
                <?php
                $query = $sql->query('SELECT * FROM `admins` WHERE `discord_id`="'.$_SESSION['id'].'";');
                if($query->num_rows > 0){
                    ?>
                        <div style="margin-top: 10px;" id="admin"><a href="./admin.php" id="btn_admin">Panel administratora</a></div>
                    <?php
                
                }
                $row = $query->fetch_assoc();
                $_SESSION['group'] = $row['group'];
                $sql->close();
                ?>

                <div style="margin-top: 10px;" id="logout"><a href="./index.php?logout" id="btn_logout">Wyloguj się</a></div>
            </div>
            <?php
        }
        ?>
        <?php

        if($c['wl-disabled'] == true){?>
            <div class="container">
                <div class="start">
                    <?=$c['wl-msg']?>
                </div>
            </div>
        <?php } else{
        if(!isset($_SESSION['islogged'])){?>
            <div class="container">
                <div class="start">
                   <p>Nie jesteś zalogowany/a</p><a class="btn_login" href="./index.php?login">Zaloguj się za pomocą Discorda</a><p><span class="accountmustbe">Konto musi być zweryfikowane, aby zalogować się.</span></p>
                </div>
            </div>
        <?php } else{
            $sql = new mysqli($c['mysql']['ip'], $c['mysql']['user'], $c['mysql']['password'], $c['mysql']['database']);
            $row = '';
            if($result = $sql->query("SELECT * FROM `applications` WHERE `discord_id`='".$_SESSION['id']."' ORDER BY `id` DESC LIMIT 1")){
                if($result->num_rows > 0){
                    $row = $result->fetch_assoc();
                    #print_r($row);
                    if($row['status'] == 'rejected'){
                        $diff = round((strtotime(date('Y-m-d H:i:s', time())) - strtotime($row['date-checked']))/86400);
                        $again = strtotime("+3 day", strtotime($row['date-checked']));
                    }
                }
            }

            $sql->close();

            if(isset($row['status']) && $row['status'] == 'accepted'){?>
                <div class="container">
                    <div class="start">
                        Twoje podanie zostało zaakceptowane!
                    </div>
                </div>
            <?php } else if(isset($row['status']) && $row['status'] == 'rejected' && $diff < 1){?>
                <div class="container">
                    <div class="start">
                        Twoje podanie zostało odrzucone! Możesz ponownie złożyć podanie w dniu <?= date('d-m-Y', $again) ?> o godzinie <?= date('H:i:s', $again) ?>.
                    </div>
                </div>
            <?php } else if(isset($row['status']) && $row['status'] == 'waiting'){?>
                <div class="container">
                    <div class="start">
                        Twoje podanie oczekuje na rozpatrzenie!<br />W wiadomości prywatnej na discordzie dostaniesz wiadomość o statusie twojego podania.
                    </div>
                </div>
            <?php } else if(isset($row['status']) && $row['status'] == 'conversation'){?>
                <div class="container">
                    <div class="start">
                        Twoje podanie zostało zaakceptowane!<br />Zjaw się na naszym Discordzie w celu przeprowadzania rozmowy rekrutacyjnej.
                    </div>
                </div>
            <?php } else{?>
                <div class="end" id="end_info" style="display: none;">Twoje podanie zostało pomyślnie wysłane!<br>
                Wszelkie informacje o statucie twojego podania będą wysyłanie w wiadomości prywatnej na Discordzie.<br>
                Dziękujemy za złożenie podania na nasz serwer. Powodzenia!</div>
                <div class="container">
                    <div class="start">
                        Witaj <span class="mainSpan"><?=$_SESSION['username']?></span>!<br />
                        Zapewne chcesz złożyć aplikacje whitelist na nasz serwer.<br />
                        Przed tobą 15 pytań rekrutacyjnych.<br />
                        <button type="button" id="btn_ready">Zaczynajmy</button>
                        <div class="warning">
                            <div class="warn"><p><i class="fas fa-exclamation-triangle"></i><span>Uwaga</span><i class="fas fa-exclamation-triangle"></i></p></div>
                            <p>Zapisz swoją aplikację na komputerze na wypadek utracenia połączenia internetowego albo problemów z systemem.</p>
                        </div>
                    </div>
                    <div class="questions" style="display: none;">
                        <form id="application" method="post" autocomplete="off">
                            <?php
                            $i = 1;
                            foreach($c['questions'] as $key => $value){
                                echo '<section class="question">';
                                #echo '<div class="id">'.$i.'</div>';
                                if($value['type'] == 'text'){
                                    echo '<label for="q'.$i.'">'.$value['title'].'</label>';
                                    echo '<input type="text" name="q'.$i.'" id="q'.$i.'" placeholder="'.$value['description'].'"required>';
                                } else if($value['type'] == 'textarea'){
                                    echo '<label for="q'.$i.'">'.$value['title'].'</label>';
                                    echo '<textarea name="q'.$i.'" id="q'.$i.'" rows="5" placeholder="'.$value['description'].'"required></textarea>';
                                } else if($value['type'] == 'number'){
                                    echo '<label for="q'.$i.'">'.$value['title'].'</label>';
                                    echo '<input type="number" name="q'.$i.'" id="q'.$i.'" placeholder="'.$value['description'].'" required>';
                                } else if($value['type'] == 'date'){
                                    echo '<label for="q'.$i.'">'.$value['title'].'</label>';
                                    echo '<input type="date" name="q'.$i.'" id="q'.$i.'" placeholder="'.$value['description'].'"required>';
                                } else if($value['type'] == 'checkbox'){
                                    echo '<div class="checkbox">'.$value['title'].'</div>';
                                    $j = 0;
                                    foreach($value['checkboxes'] as $checkbox){
                                        echo '<div class="checkbox1">';
                                        echo '<input type="checkbox" name="q'.$i.'" id="q'.$i.'_'.$j.'" value="'.$checkbox.'">';
                                        echo '<label for="q'.$i.'_'.$j.'">'.$checkbox.'</label>';
                                        echo '</div>';
                                        $j++;
                                    }
                                } else if($value['type'] == 'radio'){
                                    echo '<div class="radio">'.$value['title'].'</div>';
                                    $j = 0;
                                    foreach($value['radios'] as $radio){
                                        echo '<div class="radio1">';
                                        echo '<input type="radio" name="q'.$i.'" id="q'.$i.'_'.$j.'" value="'.$radio.'" required>';
                                        echo '<label for="q'.$i.'_'.$j.'">'.$radio.'</label>';
                                        echo '</div>';
                                        $j++;
                                    }
                                }
                                echo '</section>';
                                $i++;
                            }
                            ?>
                            <input type="button" id="cancelSend" value="Anuluj" onclick="backToMain()">
                            <input type="submit" id="send" value="Prześlij">
                        </form>
                    </div>
                </div>
        <?php }}}?>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/noty/3.1.4/noty.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/datepicker/0.6.5/datepicker.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/datepicker/0.6.5/i18n/datepicker.pl-PL.min.js"></script>
        <script src="./assets/js/app.js"></script>
        <script>
            function backToMain() {
                $('.questions').addClass('animated zoomOut');
                if(!$('.title').is(':visible')){
                    $('.title').fadeIn(500);
                }
                $(".title").animate({padding: '40px'});
                $('#admin').fadeIn(1000);
                setTimeout(() => {
                    $('.questions').hide();
                }, 1000);
                setTimeout(() => {
                    window.location.href = "./index.php";
                }, 500);
            }
            $(document).ready(() => {
                $('#loader').fadeOut(250);
                <?php
                if(isset($_SESSION['error'])){ ?>
                setTimeout(() => {
                    new Noty({
                        layout: 'topRight',
                        theme: 'metroui',
                        type: 'error',
                        text: '<?=$_SESSION['error']?>',
                        timeout: 5000,
                        progressBar: false,
                        animation: {
                            open: 'animated fadeInDown',
                            close: 'animated fadeOutUp'
                        }
                    }).show();
                }, 250);
                <?php
                    unset($_SESSION['error']);
                } ?>
                // ref();
                // setInterval(() => {
                //     ref();
                // }, 1*1000);

            });
            $(window).bind('beforeunload', () => {
                $("#loader").fadeIn(250);
            });

            $(window).on('unload', () => {
                $("#loader").fadeIn(250);
            });
            const serverInformation = document.querySelector('#status');
            // serverInfo()
            // setInterval(() => {
            //     serverInfo() 
            // }, 300 * 1000);
            // function serverInfo() {
            //     fetch('https://cors-anywhere.herokuapp.com/http://137.74.243.234:30120/players.json/players.json', {
            //     mode: 'cors',
            //     headers: {
            //         "Content-Type": "application/json; charset=utf-8",
            //         "Accept-Encoding": "gzip, deflate",
            //         "Origin": "http://localhost:3000/",
            //         "x-requested-with": "http://localhost:3000/",
            //         'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,/;q=0.8,application/signed-exchange;v=b3',
            //         'Accept-Language': 'pl-PL,pl;q=0.9,en-US;q=0.8,en;q=0.7',
            //         'Cache-Control': 'no-cache',
            //         'Connection': 'keep-alive',
            //     }
            // }).then(res => res.json())
            // .then(function (response) {
            //     serverInformation.textContent = `Ilość graczy: ${response.length}/64`
            // }).catch(function (error) {
            //     console.log('Błąd', error)
            // });
            // }
            // function ref() {
            //     $.ajax({
            //         url: 'ajax.php',
            //         type: 'POST',
            //         data: {
            //             type: 'refreshserver'
            //         },
            //         dataType: 'json',
            //         success: function (data) {
            //             console.log(data);
            //             $('#status').html(data.message);
            //         },
            //         error: function (data) {
            //             console.log(data);
            //             $('#status').html('Error');
            //         }
            //     });
            // }
            <?php if($c['wl-disabled'] != true && isset($_SESSION['islogged'])){ ?>
            $('#btn_ready').click((e) => {
                e.preventDefault();
                $('.container .start').slideUp();
                setTimeout(() => {
                    $('.questions').show().addClass('animated zoomIn');
                    if($(window).width() > 1262){
                        $(".title").animate({padding: '25px 0 0 15px'});
                        $('#admin').fadeOut(1000);
                    } else {
                        $('.title').fadeOut(500);
                    }
                    setTimeout(() => {
                        $('.questions').removeClass('animated zoomIn');
                    }, 1500);
                }, 500);
            });
            $('#application').on('submit', (e) => {
                e.preventDefault();
                for (let i = 1; i < 16; i++) {
                    document.querySelector(`#q${i}`).value = document.querySelector(`#q${i}`).value.replace(/'/gi, "`")
                }
                setTimeout(() => {
                var form = $('#application').serializeArray();
                const steam64 = document.querySelector('#q1');
                const input = document.querySelector('#q2');
                var date=$('#q2').val().split('-');
                if(Number(date[0]) < 1900) {
                    return new Noty({
                        layout: 'topRight',
                        theme: 'metroui',
                        type: 'error',
                        text: `Wpisałeś złą datę`,
                        timeout: 5000,
                        progressBar: false,
                        animation: {
                            open: 'animated fadeInDown',
                            close: 'animated fadeOutUp'
                        }
                    }).show();
                }else if(date[0].length > 4) {
                    return new Noty({
                        layout: 'topRight',
                        theme: 'metroui',
                        type: 'error',
                        text: `Wpisałeś złą datę`,
                        timeout: 5000,
                        progressBar: false,
                        animation: {
                            open: 'animated fadeInDown',
                            close: 'animated fadeOutUp'
                        }
                    }).show();
                }else if(date[0] > 2019) {
                    return new Noty({
                        layout: 'topRight',
                        theme: 'metroui',
                        type: 'error',
                        text: `Wpisałeś złą datę`,
                        timeout: 5000,
                        progressBar: false,
                        animation: {
                            open: 'animated fadeInDown',
                            close: 'animated fadeOutUp'
                        }
                    }).show();
                }else if(steam64.value.length > 17) {
                    return new Noty({
                        layout: 'topRight',
                        theme: 'metroui',
                        type: 'error',
                        text: `Twoje SteamID64 jest za długie. Wpisałeś ${Number(steam64.value.length) - 17} cyfr za dużo.`,
                        timeout: 5000,
                        progressBar: false,
                        animation: {
                            open: 'animated fadeInDown',
                            close: 'animated fadeOutUp'
                        }
                    }).show();
                }else if(steam64.value.length < 17) {
                    return new Noty({
                        layout: 'topRight',
                        theme: 'metroui',
                        type: 'error',
                        text: `Twoje SteamID64 jest za krótkie. Brakuje ${17 - Number(steam64.value.length)} cyfr`,
                        timeout: 5000,
                        progressBar: false,
                        animation: {
                            open: 'animated fadeInDown',
                            close: 'animated fadeOutUp'
                        }
                    }).show();
                }else if (steam64.value == '76561197960287930') {
                    return new Noty({
                        layout: 'topRight',
                        theme: 'metroui',
                        type: 'error',
                        text: `Wpisałeś złe SteamID64`,
                        timeout: 5000,
                        progressBar: false,
                        animation: {
                            open: 'animated fadeInDown',
                            close: 'animated fadeOutUp'
                        }
                    }).show();
                }
                //console.log(form);
                $('#send').prop('disabled', true);
                $.ajax({
                    url: 'ajax.php',
                    type: 'POST',
                    data: {
                        type: 'sendapplication',
                        questions: form
                    },
                    dataType: 'json',
                    success: (data) => {
                        $('#send').prop('disabled', false);
                        if(data.type == 'error'){
                            return new Noty({
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
                        } else{
                            $('.questions').addClass('animated zoomOut');
                            if(!$('.title').is(':visible')){
                                $('.title').fadeIn(500);
                            }
                            $(".title").animate({padding: '40px'});
                            $('#admin').fadeIn(1000);
                            setTimeout(() => {
                                $('.questions').hide();
                                $("#end_info").fadeIn(500);
                            }, 1000);
                        }
                    },
                    error: (err) => {
                        $('#send').prop('disabled', false);
                        console.log(err);
                        return new Noty({
                            layout: 'topRight',
                            theme: 'metroui',
                            type: 'error',
                            text: 'Wystąpił błąd! Skontaktuj się z administratorem.',
                            timeout: 5000,
                            progressBar: false,
                            animation: {
                                open: 'animated fadeInDown',
                                close: 'animated fadeOutUp'
                            }
                        }).show();
                    }
                });
                }, 100);
            });
            <?php } ?>
        </script>
    </body>
</html>