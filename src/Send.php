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
                self::sendInterfax($config, $pdf, $number, $reference);
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

        $number = str_replace('+49', '0049', $number);
        $number = str_replace('+', '00', $number);
        $number = preg_replace('/[^\d]/', '', $number);


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
         *     timestamp timestamp default current_timestamp,
         *     status_response json default null
         * );
         */

        $db->direct('insert into fax_log (id, fax_number, reference, status, location) values ({id}, {number}, {reference}, {status}, {location}) on duplicate key update status = {status}, location = {location}', [
            'id' => $id,
            'number' => $number,
            'reference' => $reference,
            'status' => 'done',
            'location' => $faxLocation
        ]);
    }

    public static function getInterfaxFaxRecord(int $faxID): array
    {
        $config = App::get('session')->getDB()->singleRow('select * from fax_config');
        $interfax = new InterfaxClient([
            'username' => $config['username'],
            'password' => $config['password']
        ]);

        try {

            $response = $interfax->get('/outbound/faxes/' . $faxID);

            if (is_array($response) && array_key_exists('id', $response)) {
                return $response;
            }
        } catch (\RuntimeException $e) {
            if ((int) $e->getCode() === 404) {
                return [];
            }
            throw $e;
        }

        return [];
    }


    public static function requestOpenStatus()
    {
        $sql = '
        select 
            fax_log.*,
            if (locate("/outbound/faxes/",location)=1,replace(location,"/outbound/faxes/",""),null ) fax_id
        from 
            fax_log
        having 
            fax_id is not null
            and status_response is null
            and `timestamp` between now() + interval - 3 hour and now() + interval - 10 minute
        ';
        $db = App::get('session')->getDB();
        $records = $db->direct($sql);
        foreach ($records as $record) {
            $status = self::getInterfaxFaxRecord((int)$record['fax_id']);
            if (count($status) > 0) {
                $db->direct('update fax_log set status_response = {response} where id = {id}', [
                    'id' => $record['id'],
                    'response' => json_encode($status)
                ]);
            }
        }
    }
}
