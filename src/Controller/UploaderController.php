<?php
namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Aws\S3\S3Client;
use Aws\Exception\AwsException;

/*
order_id 
order_datetime 
total_order_value 
average_unit_price
distinct_unit_count
total_units_count
customer_state
*/

// constant
define("FILETOOLARGE", "File too large");
define("EXTNOTALLOW", "extention not allowed");

class UploaderController extends AbstractController
{
    // config
    private $maxsize = 1024 * 1000000;
    private $fileTypeAllow = array("jsonl","json"); 


    /** 
     * @Route("/uploader") 
    */
    public function index(): Response
    {
        return $this->render('uploader/fileinput.html.twig');
    }

    /** 
     * @Route("/uploader/postfile") 
    */
    public function postfile(Request $request) {
        ini_set('post_max_size', '10M');
        if ($request) {
            // get input file 
            $uploadedFile = $request->files->get('filename');

            // get filename 
            $path = $uploadedFile->getClientOriginalName();
            $ext = pathinfo($path, PATHINFO_EXTENSION);

            // check extention
            if (!in_array($ext, $this->fileTypeAllow)) {
                $err = EXTNOTALLOW;
                return $err;
            }

            // file size validation up to 1 TB
            if ($uploadedFile->getSize() > $this->maxsize) {
                $err = FILETOOLARGE;
                return $err;
            }

            // read file input
            $data = file_get_contents($uploadedFile);

            // read line by lines data
            $lines =  explode(PHP_EOL,$data);
            $arr = array();
            
            foreach($lines as $line) {
                $data = json_decode($line, true);
                $count = 0;
                if (isset($data['order_id'])) {
                    $arr['order_id'] = $data['order_id'];
                    $arr['order_datetime'] = date(DATE_ISO8601, strtotime($data['order_date']));
                    $arr['total_order_value'] = $this->sumLineItemsWithDiscountApplied($data["items"], $data["discounts"]);
                    $arr['average_unit_price'] = $this->getAverageUnitPrice($data["items"]);
                    $arr['distinct_unit_count'] = "";
                    $arr['total_units_count'] = $this->getTotalUnitCount($data["items"]);
                    $arr['customer_state'] =  $this->getCustomerState($data["customer"]);
                    $items[] = $arr;
                }   
            }
            $this->array2csv($items);

            die();
        }
    }


    function sumLineItemsWithDiscountApplied($items, $discounts) {
        $price = $discount = 0;
        // calculate line items price
        foreach ($items as $item) {
            $price = $price + $item["unit_price"];
        }
        // calculate discounts
        foreach($discounts as $disc) {
            $discount = $discount + $disc["value"];
        }
        $sum = $price - $discount;
        return $sum;
    }

    function getAverageUnitPrice($items) {
        $price = 0;
        foreach ($items as $item) {
            $price = $price + $item["unit_price"];
        }
        $average = $price / sizeof($items);
        return $average;
    }

    function getTotalUnitCount($items) {
        return count($items);
    }

    function getCustomerState($customer) {
        return $customer["shipping_address"]["state"];
    }

    function array2csv($data)
    {
        $output = fopen("php://output",'w') or die("Can't open php://output");
        header("Content-Type:application/csv"); 
        header("Content-Disposition:attachment;filename=pressurecsv.csv"); 
        fputcsv($output, array('order_id','order_datetime','total_order_value', 'average_unit_price','distinct_unit_count', 'total_units_count', 'customer_state'));
        foreach($data as $item) {
            fputcsv($output, $item);
        }
        fclose($output) or die("Can't close php://output");
    }


    

}