<?php
require '../vendor/autoload.php';

use GuzzleHttp\Client;
use Slim\App;
use Slim\Http\Request;
use Slim\Http\Response;

$config = require './config.php';
$app = new App();

$client = new Client([
    'base_uri' => $config['apiUrl']
]);

// Commands
const COMMANDS = [
    'TEXT' => 'Simple text message',
    'IMAGE' => 'Send image',
    'DOCUMENT' => 'Send document',
    'VIDEO' => 'Send video',
    'CONTACT' => 'Send contact',
    'PRODUCT' => 'Send product',
    'GROUP_CREATE' => 'Create group',
    'GROUP_TEXT' => 'Simple text message for the group',
    'GROUPS_IDS' => 'Get the id\'s of your three groups'
];

// Url files
const FILES = [
    'IMAGE' => './files/file_example_JPG_100kB.jpg',
    'DOCUMENT' => './files/file-example_PDF_500_kB.pdf',
    'VIDEO' => './files/file_example_MP4_480_1_5MG.mp4',
    'VCARD' => './files/sample-vcard.txt'
];

function sendWhapiRequest($endpoint, $params = [], $method = 'POST')
{
    global $config, $client;

    $url = $config['apiUrl'] . '/' . $endpoint;
    $options = [
        'headers' => [
            'Authorization' => 'Bearer ' . $config['token'],
        ],
    ];

    if ($params && count($params) > 0) {
        if ($method === 'GET') {
            $url .= '?' . http_build_query($params);
        } else {
            if(isset($params['media'])){
                $options['multipart'] = toFormData($params);
            }else{
                $options['headers']['Content-Type'] = 'application/json';
                $options['body'] = json_encode($params);
            }
        }
    }

    echo '$options: ' . print_r($options, true);
    $response = $client->request($method, $url, $options);
    $json = json_decode($response->getBody()->getContents(), true);
    echo 'Whapi response: ' . print_r($json, true);
    return $json;
}

function toFormData($params)
{
    $multipart = [];
    foreach ($params as $name => $contents) {
        $multipart[] = ['name' => $name, 'contents' => $contents];
    }
    return $multipart;
}

function setHook()
{
    global $config;
    if ($config['botUrl']) {
        sendWhapiRequest('settings', [
            'webhooks' => [
                [
                    'url' => $config['botUrl'],
                    'events' => [
                        [
                            'type' => 'message',
                            'method' => 'post'
                        ]
                    ],
                    'mode' => 'body'
                ]
            ]
        ], 'PATCH');
    }
}

$app->get('/', function (Request $request, Response $response) {
    return $response->write('Bot is running');
});

$app->post('/messages', function (Request $request, Response $response) use ($config) {
    $data = json_decode($request->getBody(), true);
    $messages = $data['messages'] ?? [];

    foreach ($messages as $message) {
        if ($message['from_me']) {
            continue;
        }

        $sender = ['to' => $message['chat_id']];
        $endpoint = 'messages/text';
        $textBody = trim($message['text']['body'] ?? '');
        $commandIndex = is_numeric($textBody) ? (int)$textBody - 1 : null;
        $commands = array_keys(COMMANDS);
        $command = $commands[$commandIndex] ?? null;

        switch ($command) {
            case 'TEXT':
                $sender['body'] = 'Simple text message';
                break;

            case 'IMAGE':
                $sender['caption'] = 'Text under the photo.';
                $sender['media'] = fopen(FILES['IMAGE'], 'r');
                $endpoint = 'messages/image';
                break;

            case 'DOCUMENT':
                $sender['caption'] = 'Text under the document.';
                $sender['media'] = fopen(FILES['DOCUMENT'], 'r');
                $endpoint = 'messages/document';
                break;

            case 'VIDEO':
                $sender['caption'] = 'Text under the video.';
                $sender['media'] = fopen(FILES['VIDEO'], 'r');
                $endpoint = 'messages/video';
                break;

            case 'CONTACT':
                $sender['name'] = 'Whapi Test';
                $sender['vcard'] = file_get_contents(FILES['VCARD']);
                $endpoint = 'messages/contact';
                break;

            case 'PRODUCT':
                // Replace with your product ID
                $endpoint = "business/products/{$config['product']}";
                break;

            case 'GROUP_CREATE':
                $groupSettings = [
                    'subject' => 'Whapi.Cloud Test',
                    'participants' => [$message['from']]
                ];
                $groupResponse = sendWhapiRequest('groups', $groupSettings, 'POST');
                $sender['body'] = $groupResponse['group_id'] ? "Group created. Group id: {$groupResponse['group_id']}" : 'Error';
                $endpoint = 'messages/text';
                break;

            case 'GROUP_TEXT':
                $sender['to'] = $config['group'];
                $sender['body'] = 'Simple text message for the group';
                break;

            case 'GROUPS_IDS':
                $groupsResponse = sendWhapiRequest('groups', ['count' => 3], 'GET');
                if (!empty($groupsResponse['groups'])) {
                    $groupIds = array_map(function ($group, $i) {
                        return ($i + 1) . ". {$group['id']} - {$group['name']}";
                    }, $groupsResponse['groups'], array_keys($groupsResponse['groups']));
                    $sender['body'] = implode(",\n ", $groupIds);
                } else {
                    $sender['body'] = 'No groups';
                }
                break;

            default:
                $sender['body'] = "Hi. Send me a number from the list. Don't forget to change the actual data in the code! \n\n" .
                    implode("\n", array_map(function ($text) {
                        static $i = 0;
                        $i++;
                        return ($i) . ". $text";
                    }, COMMANDS, array_keys(COMMANDS)));
                break;
        }

        try {
            sendWhapiRequest($endpoint, $sender);
        } catch (\Exception $e) {
            error_log($e->getMessage());
            return $response->withStatus(500)->write('Error: ' . $e->getMessage());
        }

    }

    return $response->withStatus(200)->write('Ok');
});


$app->run();
