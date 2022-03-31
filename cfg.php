<?php

$c = array();

$c['name'] = '';
$c['surfix'] = '-';
$c['keywords'] = '';
$c['description'] = '';
$c['wl-disabled'] = false;
$c['wl-msg'] = '';
$c['serverip'] = '';

$c['mysql']['ip'] = '';
$c['mysql']['user'] = '';
$c['mysql']['password'] = '';
$c['mysql']['database'] = '';

$c['oauth2']['client_id'] = '';
$c['oauth2']['client_secret'] = '';
$c['oauth2']['redirectUri_index'] = '';
$c['oauth2']['redirectUri_admin'] = '';
$c['oauth2']['inviteLink'] = '';

$c['questions'] = array(
    1 => array(
        'title' => 'Podaj swoje SteamID64',
        'description' => 'SteamID64',
        'type' => 'number'
    ),
    2 => array(
        'title' => 'Podaj datę urodzenia',
        'description' => '',
        'type' => 'date',
    ),
    3 => array(
        'title' => 'Podaj link do twojego profilu na forum.',
        'description' => 'https://...',
        'type' => 'textarea'
    ),
    4 => array(
        'title' => 'Czym jest dla ciebie Roleplay?',
        'description' => 'Minimum 2 zdania.',
        'type' => 'textarea'
    ),
    5 => array(
        'title' => 'Streamujesz lub nagrywasz content na swoje kanały?',
        'description' => 'Jeśli tak, podaj linki, czy też nagrania z przykładem twojego RP.',
        'type' => 'textarea'
    ),
    6 => array(
        'title' => 'Przedstaw twoje doświadczenie z RP oraz opisz odgrywane przez ciebie wcześniej postacie.',
        'description' => '',
        'type' => 'textarea'
    ),
    7 => array(
        'title' => 'Jakie postacie zamierzasz odgrywać na naszym serwerze, a następnie opisz je.',
        'description' => 'Minimum 5 zdań.',
        'type' => 'textarea'
    ),
    8 => array(
        'title' => 'Czym jest OOC oraz IC i czym się różnią?',
        'description' => '',
        'type' => 'textarea'
    ),
    9 => array(
        'title' => 'Jakim rodzajem czatu jest komenda /tweet i do czego jest używana?',
        'description' => '',
        'type' => 'textarea'
    ),
    10 => array(
        'title' => 'Czym jest BW? Rozwiń skrót oraz go opisz.',
        'description' => '',
        'type' => 'textarea'
    ),
    11 => array(
        'title' => 'Czym jest metagaming? I czy można go używać w grze?',
        'description' => '',
        'type' => 'textarea'
    ),
    12 => array(
        'title' => 'Czym jest powergaming?',
        'description' => '',
        'type' => 'textarea'
    ),
    13 => array(
        'title' => 'Kiedy twoja postać jest zobowiązana zapomnieć o sytuacji, która miała miejsce przed zrespieniem się w szpitalu?',
        'description' => '',
        'type' => 'textarea'
    ),
    14 => array(
        'title' => 'Co zrobisz gdy dostaniesz crasha, jak powiadomisz o tym administrację?',
        'description' => '',
        'type' => 'textarea'
    ),
    15 => array(
        'title' => 'Skąd wiesz o serwerze?',
        'description' => '',
        'type' => 'textarea'
    ),
    16 => array(
        'title' => 'Czy akceptujesz regulamin?',
        'description' => '',
        'type' => 'radio',
        'radios' => array(
            'Tak',
            'Nie'
        ),
        'correct-radio' => array('Tak'),

        'correct' => false,
        'msg' => 'Daj odpowiedź "Tak"'
    ),
    17 => array(
        'title' => 'Czy przeszedłeś mutacje?',
        'description' => '',
        'type' => 'radio',
        'radios' => array(
            'Tak',
            'Nie'
        ),
        'correct-radio' => array('Tak'),

        'correct' => false,
        'msg' => 'Daj odpowiedź "Tak"'
    ),
);

# PERMS:
# accept_app
# discard_app
# have_conversation
# remove_app
# add_admins
# remove_admins
# add_to_block
# remove_from_block

$c['admin']['groups'] = array(
    'Developer' => array('accept_app', 'discard_app', 'have_conversation', 'remove_app', 'add_admins', 'remove_admins', 'add_to_block', 'remove_from_block'),
    'Starszy Admin' => array('accept_app', 'discard_app', 'have_conversation', 'add_to_block'),
    'Admin' => array('accept_app', 'discard_app', 'have_conversation', 'add_to_block'),
    'Support' => array('accept_app', 'discard_app', 'have_conversation'),
    'Trial Support' => array('accept_app', 'discard_app', 'have_conversation'),
);
