<?php
declare(strict_types=1);
namespace Glued\Controllers;

use Exception;
use horstoeko\zugferd\ZugferdDocumentPdfReader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ServiceController extends AbstractController
{

    /**
     * Returns an exception.
     * @param  Request  $request
     * @param  Response $response
     * @param  array    $args
     * @return Response Json result set.
     */
    public function stub(Request $request, Response $response, array $args = []): Response {
        throw new Exception('Stub method served where it shouldn\'t. Proxy misconfigured?');
    }

    /**
     * Returns a health status response.
     * @param  Request  $request
     * @param  Response $response
     * @param  array    $args
     * @return Response Json result set.
     */
    public function health(Request $request, Response $response, array $args = []): Response {
        $params = $request->getQueryParams();
        $data = [
                'timestamp' => microtime(),
                'status' => 'OK',
                'params' => $params,
                'service' => basename(__ROOT__),
                'provided-for' => $_SERVER['HTTP_X-GLUED-AUTH-UUID'] ?? 'anon'
        ];
        return $response->withJson($data, options: JSON_UNESCAPED_SLASHES);
    }

    /**
     * Extracts metadata from factur-x pdfs
     * @param  Request  $request
     * @param  Response $response
     * @param  array    $args
     * @return Response Json result set.
     */
    public function extract(Request $request, Response $response, array $args = []): Response {
        $params = $request->getQueryParams();
        $data = [];

        $z = new ZugferdDocumentPdfReader;
        $path = $this->settings['glued']['datapath'].'/glued-facturx/data';
        $files = $files = glob($this->settings['glued']['datapath'].'/glued-facturx/data/*.pdf');
        foreach ($files as $f) {
            $document = $z::readAndGuessFromFile($f);
            $document->getDocumentInformation($documentno, $documenttypecode, $documentdate, $invoiceCurrency, $taxCurrency, $documentname, $documentlanguage, $effectiveSpecifiedPeriod);

            $d['document']['no'] = $documentno;
            $d['document']['type'] = $documenttypecode;
            $d['document']['date'] = $documentdate->format("Y-m-d");
            $d['document']['invoice-currency'] = $invoiceCurrency;
            $d['document']['tax-currency'] = $taxCurrency;

            if ($document->firstDocumentPosition()) {
                echo "\r\nDocument positions\r\n";
                echo "----------------------------------------------------------------------\r\n";
                do {
                    $document->getDocumentPositionGenerals($lineid, $linestatuscode, $linestatusreasoncode);
                    $document->getDocumentPositionProductDetails($prodname, $proddesc, $prodsellerid, $prodbuyerid, $prodglobalidtype, $prodglobalid);
                    $document->getDocumentPositionGrossPrice($grosspriceamount, $grosspricebasisquantity, $grosspricebasisquantityunitcode);
                    $document->getDocumentPositionNetPrice($netpriceamount, $netpricebasisquantity, $netpricebasisquantityunitcode);
                    $document->getDocumentPositionLineSummation($lineTotalAmount, $totalAllowanceChargeAmount);
                    $document->getDocumentPositionQuantity($billedquantity, $billedquantityunitcode, $chargeFreeQuantity, $chargeFreeQuantityunitcode, $packageQuantity, $packageQuantityunitcode);

                    echo " - Line Id:                        {$lineid}\r\n";
                    echo " - Product Name:                   {$prodname}\r\n";
                    echo " - Product Description:            {$proddesc}\r\n";
                    echo " - Product Buyer ID:               {$prodbuyerid}\r\n";
                    echo " - Product Gross Price:            {$grosspriceamount}\r\n";
                    echo " - Product Gross Price Basis Qty.: {$grosspricebasisquantity} {$grosspricebasisquantityunitcode}\r\n";
                    echo " - Product Net Price:              {$netpriceamount}\r\n";
                    echo " - Product Net Price Basis Qty.:   {$netpricebasisquantity} {$netpricebasisquantityunitcode}\r\n";
                    echo " - Quantity:                       {$billedquantity} {$billedquantityunitcode}\r\n";
                    echo " - Line amount:                    {$lineTotalAmount}\r\n";

                    if ($document->firstDocumentPositionTax()) {
                        echo " - Position Tax(es)\r\n";
                        do {
                            $document->getDocumentPositionTax($categoryCode, $typeCode, $rateApplicablePercent, $calculatedAmount, $exemptionReason, $exemptionReasonCode);
                            echo "   - Tax category code:            {$categoryCode}\r\n";
                            echo "   - Tax type code:                {$typeCode}\r\n";
                            echo "   - Tax percent:                  {$rateApplicablePercent}\r\n";
                            echo "   - Tax amount:                   {$calculatedAmount}\r\n";
                        } while ($document->nextDocumentPositionTax());
                    }

                    if ($document->firstDocumentPositionAllowanceCharge()) {
                        echo " - Position Allowance(s)/Charge(s)\r\n";
                        do {
                            $document->getDocumentPositionAllowanceCharge($actualAmount, $isCharge, $calculationPercent, $basisAmount, $reason, $taxTypeCode, $taxCategoryCode, $rateApplicablePercent, $sequence, $basisQuantity, $basisQuantityUnitCode, $reasonCode);
                            echo "   - Information\r\n";
                            echo "     - Actual Amount:                {$actualAmount}\r\n";
                            echo "     - Type:                         " . ($isCharge ? "Charge" : "Allowance") . "\r\n";
                            echo "     - Tax category code:            {$taxCategoryCode}\r\n";
                            echo "     - Tax type code:                {$taxTypeCode}\r\n";
                            echo "     - Tax percent:                  {$rateApplicablePercent}\r\n";
                            echo "     - Calculated percent:           {$calculationPercent}\r\n";
                            echo "     - Basis amount:                 {$basisAmount}\r\n";
                            echo "     - Basis qty.:                   {$basisQuantity} {$basisQuantityUnitCode}\r\n";
                        } while ($document->nextDocumentPositionAllowanceCharge());
                    }

                    echo "\r\n";
                } while ($document->nextDocumentPosition());
            }

            $data[] = $d;
        }
        return $response->withJson($data, options: JSON_UNESCAPED_SLASHES);
    }


}
