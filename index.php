<?php
require __DIR__ .'/vendor/autoload.php';

$client = new \RetailCrm\ApiClient(
    'url',
    'key',
    \RetailCrm\ApiClient::V5
);

logger('request.log',$_REQUEST);

if (!isset($_GET['id']))
    die('Nein');

$order = $client->request->ordersGet($_GET['id'],'id');
$customer = $client->request->customersGet($order['order']['customer']['id'],'id');

if ($order['order']['orderMethod'] == 'shopping-cart')
    die('order method invalid');

$sale = 0;
if (strlen($customer['customer']['customFields']['promokod']) == 5)
    $sale = 10;
elseif(strlen($customer['customer']['customFields']['promokod']) == 6)
    $sale = 15;

$items = $order['order']['items'];
foreach ($items as &$item) {
    //Проверка на наличие скидки у товара
    if ($item['discountTotal'] != null)
        continue;
    $storeProduct = $client->request->storeProducts([
        'offerIds' => [
            $item['offer']['id']
        ]
    ],1,100);
    /*
     * Проверка товара на принадлежность группе Без названия
     */
    $groupsIds = [];
    foreach ($storeProduct['products'][0]['groups'] as $group)
        $groupsIds[] = $group['id'];
    $productGroup = $client->request->storeProductsGroups([
        'ids' => $groupsIds
    ],1,100);
    $groupCheck = false;
    foreach ($productGroup['productGroup'] as $group) {
        if ($group['name'] == 'Без названия') {
            $groupCheck = true;
            break;
        }
    }
    if ($groupCheck)
        continue;
    /*
     * Проверка на критические слова в названии «Набор», «СПЕЦЦЕНА», «Таблетница», «Банка» и «Салфетки»
     */
    if (strpos($item['offer']['displayName'],'Набор') !== false or strpos($item['offer']['displayName'],'СПЕЦЦЕНА') !== false or strpos($item['offer']['displayName'],'Таблетница') !== false or
        strpos($item['offer']['displayName'],'Банка') !== false or strpos($item['offer']['displayName'],'Салфетки') !== false or strpos($item['offer']['displayName'],'салфетки') !== false)
        continue;


    $item['discountManualPercent'] = $sale;
}

$orderEdit = $client->request->ordersEdit([
    'id' => $order['order']['id'],
    'items' => $items
],'id',$order['order']['site']);

logger('orderEdit.log',[
    'date' => date('Y-m-d H:i:s'),
    'data' => print_r($orderEdit,true)
]);

function logger($filename = 'noname.log',$data = array()) {
    if (!is_dir(__DIR__ .'/logs'))
        mkdir(__DIR__ .'/logs');
    $fd = fopen(__DIR__ .'/logs/' . $filename,'a');
    fwrite($fd,print_r($data,true) . "\n");
    fclose($fd);
}
