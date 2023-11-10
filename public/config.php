<?php
return [
    // API endpoint URL
    "apiUrl" => "https://gate.whapi.cloud",
    // API token from your channel
    "token" => "YOUR CHANNEL TOKEN ",
    // The ID of the group to which we will send the message. Use to find out the ID: https://whapi.readme.io/reference/getgroups
    "group" => '120363167596599603@g.us',
    // The ID of the product we will send for the example. Create a product in your WhatsApp and find out the product ID: https://whapi.readme.io/reference/getproducts
    "product" => '6559353560856703',
    // Bot`s URL - Link to your server. At ( {server link}/messages ), when POST is requested, processing occurs in index.php
    "botUrl" => "https://yoursite.com/messages"
];
