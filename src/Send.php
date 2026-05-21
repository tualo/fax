<?php

namespace Tualo\Office\FAX;

use Interfax\Client as InterfaxClient;

use Tualo\Office\Basic\TualoApplication as App;
use Tualo\Office\DS\DSModel;
use Tualo\Office\DS\DSCreateRoute;
use Tualo\Office\DS\DSReadRoute;
use PHPMailer\PHPMailer\PHPMailer;
use Ramsey\Uuid\Uuid;

class Send
{
    public static function sendPDF(string $pdf, string $number, string $reference = ''): bool
    {
        $db = App::get('session')->getDB();
        $config = $db->singleRow('select * from fax_config');

        switch ($config['id']) {
            case 'interfax':
                self::sendInterfax($config, $pdf, $number);
                break;
            default:
                throw new \Exception('Fax-Provider not found!');
        }
        return true;
    }

    public static function sendInterfax(array $config, string $pdf, string $number, string $reference = '')
    {
        $db = App::get('session')->getDB();
        $id =  (Uuid::uuid4())->toString();

        $faxLocation = 'unknown';
        $db->direct('insert into fax_log (id, fax_number, reference, status, location) values ({id}, {number}, {reference}, {status}, {location}) on duplicate key update status = {status}, location = {location}', [
            'id' => $id,
            'number' => $number,
            'reference' => $reference,
            'status' => 'started',
            'location' => $faxLocation
        ]);


        $interfax = new InterfaxClient([
            'username' => $config['username'],
            'password' => $config['password']
        ]);
        $fax = $interfax->deliver(
            [
                'faxNumber' => $number,
                'file' => $pdf,
                'reference' => $reference
            ]
        );
        $faxLocation = $fax->getLocation();
        // print_r($fax);

        /**
         * 
         * create table fax_log (
         *     id varchar(36) primary key default (uuid()),
         *     fax_number varchar(255),
         *     reference varchar(255),
         *     status varchar(255),
         *     location varchar(255),
         *     timestamp timestamp default current_timestamp
         * );
         */

        $db->direct('insert into fax_log (id, fax_number, reference, status, location) values ({id}, {number}, {reference}, {status}, {location}) on duplicate key update status = {status}, location = {location}', [
            'id' => $id,
            'number' => $number,
            'reference' => $reference,
            'status' => 'started',
            'location' => $faxLocation
        ]);
    }
}
