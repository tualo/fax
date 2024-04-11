<?php


namespace Tualo\Office\FAX\Routes;

use Tualo\Office\FAX\Send;
use Tualo\Office\Basic\TualoApplication as App;
use Tualo\Office\Basic\Route as BasicRoute;
use Tualo\Office\Basic\IRoute;
use Tualo\Office\DS\DSTable;
use Tualo\Office\PUG\PUG AS P;
use Tualo\Office\RemoteBrowser\RemotePDF;
use DOMDocument;
use Tualo\Office\Mail\SMTP;
class PUG implements IRoute{
    public static function register()
    {
        BasicRoute::add('/fax/renderpug', function ($matches) {
            $db = App::get('session')->getDB();
            
            try {

                App::set("pugCachePath", App::get("basePath").'/cache/'.$db->dbname.'/cache' );

                $postdata = json_decode(file_get_contents("php://input"),true);
                if(is_null($postdata)) throw new \Exception('Payload not readable');
                
                if (!isset($postdata['__sendfax_template'])) throw new \Exception('Template not set');
                if (!isset($postdata['__sendfax_info'])) throw new \Exception('Info not set');
                $template=$postdata['__sendfax_template'];
                

                $infotable = new DSTable($db,$postdata['__sendfax_info']);

                if (!isset($postdata['__sendfax_filterfields'])){
                    $f=[];
                    foreach($postdata as $key => $value) $f[] = $key;
                    $postdata['__sendfax_filterfields'] = implode(',',$f);
                }
                $postdata['__sendfax_filterfields'] = explode(',',$postdata['__sendfax_filterfields']);
                foreach($postdata as $key => $value) {
                    if (in_array($key,$postdata['__sendfax_filterfields']))
                    $infotable->filter($key,'=',$value);
                }
                    
                $infotable->limit(1)->read();
                if ($infotable->empty()) throw new \Exception('Info not found');
                $info = $infotable->getSingle();
                $info['mail_addresses']=json_decode($info['mail_addresses'],true);
                App::result('info', $info);
                P::exportPUG($db);

                $html = P::render($template,$postdata);

                $subject = '';
                $dom = new DOMDocument();

                if($dom->loadHTML($html)) {
                    $list = $dom->getElementsByTagName("title");
                    if ($list->length > 0) {
                        $subject = $list->item(0)->textContent;
                    }
                }
                $attachments=[];
                $attachment_ids = [];
                if (isset($postdata['__pug_attachments']) && $postdata['__pug_attachments']!=''){

                    $res = RemotePDF::get($postdata['__table_name'],$postdata['__pug_attachments'],$postdata['__id'],true);
                    if (isset($res['filename'])){
                        $attachments[] = [
                            'filename'=>basename($res['filename']),
                            'title'=>$res['title'],
                            'contenttype'=>$res['contenttype'],
                            'filesize'=>$res['filesize'],
                        ];
                        $attachment_ids[] = basename($res['filename']);
                    }
                }


                if (isset($postdata['__ds_files_attachments'])){
                    if (is_string($postdata['__ds_files_attachments'])){
                        $postdata['__ds_files_attachments'] = json_decode($postdata['__ds_files_attachments'],true);
                    }

                    foreach($postdata['__ds_files_attachments'] as $file_id){
                        $sql = 'select 
                        ds_files.file_id,
                        ds_files.name,
                        ds_files_data.data
                        from ds_files
                        join ds_files_data
                        on ds_files.file_id = ds_files_data.file_id';
                        $res = $db->singleRow($sql,['file_id'=>$file_id]);
                        if (isset($res['data'])){
                            list($mime,$data) = explode(',',$res['data']);
                            $attachments[] = [
                                'filename'=>$res['name'],
                                'title'=>$res['name'],
                                'contenttype'=>$mime,
                                'filesize'=>strlen($data),
                            ];
                            $attachment_ids[] = $res['file_id'];
                            file_put_contents(App::get("tempPath").'/'.$res['file_id'],base64_decode($data));
                        }


                    }

                }
                // unlink($res['filename']);

                App::result('postdata', $postdata);
                App::result('attachments', $attachments);
                App::result('data', [
                    // 'mailfrom'=>$db->singleValue('select getSessionUser() v',[],'v'),
                    // 'mailsubject'=>$subject,
                    'to'=>$info['fax_addresses'][0],
                    // 'mailbody' => $html,
                    'attachments' => $attachment_ids,
                ]);

                App::result('html', $html);
                App::result('success', true);
            } catch (\Exception $e) {
                App::result('msg', $e->getMessage());
            }
            App::contenttype('application/json');
        }, ['put'], true);


        BasicRoute::add('/fax/(?P<tablename>[\w\-\_]+)/(?P<template>[\w\-\_]+)/(?P<id>.+)/(?P<number>[\w\-\_]+)', function ($matches) {
            try{
                $filedata = RemotePDF::get($matches['tablename'],$matches['template'],$matches['id']);
                if (isset($filedata['filename'])){
                    $res = Send::sendPDF($filedata['filename'],$matches['number']);
                    if ($res){
                        App::result('success', true);
                    }else{
                        App::result('success', false);
                    }
                }else{
                    App::result('msg', 'File not found');
                    App::result('success', false);
                }
            }catch(\Exception $e){
                App::result('success', false);
                App::result('msg', $e->getMessage());
            }
        }, ['get'], true);

        BasicRoute::add('/fax/sendpug', function ($matches) {
            App::contenttype('application/json');
            try{
                $db = App::get('session')->getDB();
                $data = json_decode(file_get_contents("php://input"),true);
                if(is_null($data)) throw new \Exception('Payload not readable');
                
                if(!isset($data['to'])) throw new \Exception('To not set');
                
                
                $to_list = explode(';',App::configuration('fax','force_fax_to',$data['to']));
                foreach($to_list as $to){
                    $to = trim($to);
                    if ($to!=''){
                        $data['to'] = $to;
                        foreach($data['attachments'] as $attachment){
                            $res = Send::sendPDF(App::get("tempPath").'/'.$attachment,$data['to']);
                        }
                    }
                }
                
                App::result('success', true);
            } catch (\Exception $e) {
                App::contenttype('application/json');
                App::result('msg', $e->getMessage());
            }
        }, ['put','post'], true);
    }
}
