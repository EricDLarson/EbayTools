<?php
require '../../libs/vendor/autoload.php';

// Uses the DTS Ebay SDK for php:
// https://github.com/davidtsadler/ebay-sdk-php

use \DTS\eBaySDK\Constants;
use \DTS\eBaySDK\Trading\Services;
use \DTS\eBaySDK\Trading\Types;
use \DTS\eBaySDK\Trading\Enums;
use \DTS\eBaySDK\Trading\Enums\BestOfferActionCodeType;

$config = require '../../config/ebay-config.php';

$sdk = new DTS\eBaySDK\Sdk($config);

$service = new Services\TradingService([
  'credentials' => $config['production']['credentials'],
  'siteId'      => Constants\SiteIds::US
]);

// Process Accept/Decline request
if (isset($_REQUEST['action'])) {
  $request = new Types\RespondToBestOfferRequestType();
  
  $request->RequesterCredentials = new Types\CustomSecurityHeaderType();
  $request->RequesterCredentials->eBayAuthToken =
    $config['production']['authToken'];
  
  if ($_REQUEST['action'] == 'accept') {
    $request->Action = BestOfferActionCodeType::C_ACCEPT;
    
  } else if ($_REQUEST['action'] == 'decline') {
    $request->Action = BestOfferActionCodeType::C_DECLINE;
  }
  
  $request->ItemID = $_REQUEST['itemid'];
  $request->BestOfferID = [$_REQUEST['offerid']];
  
  $response = $service->respondToBestOffer($request);
  print_r($response);
  
  exit();
}

// Display the list of open offers
$request = new Types\GetBestOffersRequestType();

$request->RequesterCredentials = new Types\CustomSecurityHeaderType();
$request->RequesterCredentials->eBayAuthToken =
  $config['production']['authToken'];
$request->DetailLevel = ['ReturnAll'];

$request->Pagination = new Types\PaginationType();
$request->Pagination->EntriesPerPage = 20; // 20 seems to be max for this call
$request->Pagination->PageNumber = 1;
$response = $service->getBestOffers($request);

$totalpages = $response->PaginationResult->TotalNumberOfPages;
$totaloffers = $response->PaginationResult->TotalNumberOfEntries;
?>

<html>
  <head>
    <title>All Pending Ebay Offers</title>
    <script>
     function ajaxFunction(action) {
       var xmlHttp = new XMLHttpRequest();
       xmlHttp.open("GET", action);
       xmlHttp.send(null);
     }
    </script>
  </head>
  <body>
    <h2><?= $totaloffers ?> Offers</h2>
    <table border=1>
      <?php
      $offertotal = 0;
      
      for ($page = 1; $page <= $totalpages; $page++) {
        if (count($response->ItemBestOffersArray->ItemBestOffers) == 0) {
          $page = 100;
          continue;
        }
        
        foreach ($response->ItemBestOffersArray->ItemBestOffers as $offer) {
          $count = count($offer->BestOfferArray->BestOffer);
          
          $offerprice = $offer->BestOfferArray->BestOffer[0]->Price->value;
          $myprice = $offer->Item->BuyItNowPrice->value;
          $offerpct = sprintf("%.2f",$offerprice/$myprice * 100);
          
          if ($count == 1) { 
            $offertotal += $offerprice; ?>
        
        <tr>
          <td>
            <a href="https://ofr.ebay.com/offerapp/bo/showOffers/<?= $offer->Item->ItemID ?>" target="_blank">
              <img src="http://i.ebayimg.com/images/i/<?= $offer->Item->ItemID ?>-0-1/s-l150/p.jpg"></a>
          </td>
          <td>List: $<?= $myprice ?><br/>Offer:
            <b>
              <?php if ($offerpct < 60) {
                print "<font color=red>\$$offerprice</font>";
              } else {
                printf("$%.2f", $offerprice);
              } ?>
            </b>
            <br/>
            <a href="javascript:ajaxFunction('?offerid=<?= $offer->BestOfferArray->BestOffer[0]->BestOfferID ?>&itemid=<?= $offer->Item->ItemID ?>&action=accept')" target=_blank>Accept</a><br/><br/>
            <a href="javascript:ajaxFunction('?offerid=<?= $offer->BestOfferArray->BestOffer[0]->BestOfferID ?>&itemid=<?= $offer->Item->ItemID ?>&action=decline')" target=_blank>Decline</a><br/>
          </td>
          <td>
            <?= $offer->BestOfferArray->BestOffer[0]->Buyer->UserID ?>
            (<?= $offer->BestOfferArray->BestOffer[0]->Buyer->FeedbackScore ?>)
            <br/>
            <?= $offer->BestOfferArray->BestOffer[0]->Buyer->Email ?><br/>
            <?php if ($offer->BestOfferArray->BestOffer[0]->Buyer->ShippingAddress->CountryName != 'US') {
              print $offer->BestOfferArray->BestOffer[0]->Buyer->ShippingAddress->CountryName . "<br/>";
            } ?>
            <?= $offer->BestOfferArray->BestOffer[0]->Buyer->ShippingAddress->StateOrProvince ?> - <?= $offer->BestOfferArray->BestOffer[0]->Buyer->ShippingAddress->PostalCode ?>
          </td>
          <td>
            <a href="https://ofr.ebay.com/offerapp/bo/showOffers/<?= $offer->Item->ItemID ?>" target="_blank">
              <?= $offer->Item->ItemID ?>
            </a>
          </td>
          <td><?= $offer->Item->Title ?></td>
        </tr>

        <?php if ($offer->BestOfferArray->BestOffer[0]->BuyerMessage) { ?>
          <tr>
            <td colspan="5">
              <?= $offer->BestOfferArray->BestOffer[0]->BuyerMessage ?>
            </td>
          </tr>
        <?php  } 
        } else { // Display link to multiple offers?>
          <tr>
            <td>
              <a href="https://ofr.ebay.com/offerapp/bo/showOffers/<?= $offer->Item->ItemID ?>" target="_blank">
                <img src="http://i.ebayimg.com/images/i/<?= $offer->Item->ItemID ?>-0-1/s-l150/p.jpg"></a>
            </td>
            <td colspan="4">
              Multiple offers:
              <a href="https://ofr.ebay.com/offerapp/bo/showOffers/<?= $offer->Item->ItemID ?>" target="_blank">
                <?= $offer->Item->ItemID ?>
              </a>
            </td>
          </tr>
        <?php }
        }
        $request->Pagination->PageNumber = $page + 1;
        $response = $service->getBestOffers($request);
        }
        ?>
    </table>
    <br/>
    <b>Offers total: <?php printf("\$%.2f",$offertotal); ?>        
  </body>
</html>
