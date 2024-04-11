<?php
namespace Tualo\Office\FAX;
use Interfax\Client as InterfaxClient;

use Tualo\Office\Basic\TualoApplication as App;
use Tualo\Office\DS\DSModel;
use Tualo\Office\DS\DSCreateRoute;
use Tualo\Office\DS\DSReadRoute;
use PHPMailer\PHPMailer\PHPMailer;


class Send {
    public static function sendPDF(string $pdf,string $number):bool{
        $db = App::get('session')->getDB();
        $config = $db->singleRow('select * from fax_config' );

        switch($config['id']){
            case 'interfax':
                self::sendInterfax($config,$pdf,$number);
            break;
            default:
                throw new \Exception('Fax-Provider not found!');
        }
        return true;
    }

    public static function sendInterfax(array $config,string $pdf,string $number){
        // $db = App::get('session')->getDB();
        // $fax = $db->singleRow('select * from fax where fax_id = {id}',['id'=>$_GET['id']]);
        $interfax = new InterfaxClient([
            'username' => $config['username'], 
            'password' => $config['password']
        ]);
        $fax = $interfax->deliver(['faxNumber' => $number, 'file' => $pdf]);
        // print_r($fax);

    }
}