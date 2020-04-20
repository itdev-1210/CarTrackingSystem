<?php
namespace App\Controller;

use App\Controller\AppController;
use Cake\ORM\TableRegistry;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use App\Utility\Traccar;

/**
 * Documents Controller
 *
 * @property \App\Model\Table\DocumentsTable $Documents
 *
 * @method \App\Model\Entity\Document[] paginate($object = null, array $settings = [])
 */
class ReportsController extends AppController
{

    var $uses = array();

    /**
     * Index method
     *
     * @return \Cake\Http\Response|void
     */
    public function index(){
        $session = $this->request->session();

        if (($session->read('_user_type') == 1) || ($session->read('_user_type') == 2)){
            return $this->redirect(['action' => 'managementdashboard']);
        }elseif ($session->read('_user_type') == 3){
            return $this->redirect(['action' => 'userdashboard']);
        }
    }

    public function overview(){
        $session = $this->request->session();
        $deviceTable = TableRegistry::get('Devices');

        $device_targetname = '';
        $device_info = array();
        $start_date = '';
        $end_date = '';

        if ($this->request->is('post')) {
            // debug($this->request->data);
            $device = $deviceTable->find('all')
                        ->where(['id' => $this->request->data['devicesid']])
                        ->toArray();
            // debug($device[0]->device_traccarid);
            // die();

            $total_distance = '';

            $obj_traccar = new \App\Utility\Traccar();
            $t_login = $obj_traccar->login();

            $device_targetname = $device[0]->device_targetname;
            // $device_id = $this->request->data['devicesid'];
            $device_id = $device[0]->device_traccarid;
            $start_date = $this->request->data['startdate'];
            $end_date = $this->request->data['enddate'];

            $from_date = date('Y-m-d H:i:s', strtotime('-330 minutes', strtotime($start_date)));
            $to_date = date('Y-m-d H:i:s', strtotime('-330 minutes', strtotime($end_date)));

            $from_date = date('c', strtotime(date($from_date)));
            $to_date = date('c', strtotime(date($to_date)));

            $from_date = substr($from_date, 0, strpos($from_date, '+')).'Z';
            $to_date = substr($to_date, 0, strpos($to_date, '+')).'Z';

            if ($t_login->responseCode == 200){
                $t_devices = $obj_traccar->positions($device_id, $from_date, $to_date);
                $device_init_info = json_decode($t_devices->response);
                // debug($device_init_info);
                // die();
                $init_time = '';
                foreach ($device_init_info as $key => $value) {
                    if ($init_time == ''){
                        $init_time = date('Y-m-d H:i:s', strtotime($value->fixTime));
                        $_address = self::get_address($value->latitude, $value->longitude);

                        $device_info[] = ['date' => date('d-M-Y', strtotime($init_time)),
                                                    'time' => date('H:i', strtotime($init_time)),
                                                    'latlong' => $value->latitude.', '.$value->longitude,
                                                    'address' => $_address,
                                                    'status' => $value->attributes->motion,
                                                    'speed' => round($value->speed, 2),
                                                    'distance' => 0];

                        $totalDistance = $value->attributes->totalDistance;
                    }else{
                        if ((strtotime(date('Y-m-d H:i:s', strtotime($value->fixTime))) -
                            strtotime($init_time)) >= 120){
                            $init_time = date('Y-m-d H:i:s', strtotime($value->fixTime));
                            $_address = self::get_address($value->latitude, $value->longitude);

                            $device_info[] = ['date' => date('d-M-Y', strtotime($init_time)),
                                                        'time' => date('H:i', strtotime($init_time)),
                                                        'latlong' => $value->latitude.', '.$value->longitude,
                                                        'address' => $_address,
                                                        'status' => $value->attributes->motion,
                                                        'speed' => round($value->speed, 2),
                                                        'distance' => $value->attributes->totalDistance - $totalDistance];
                            // $totalDistance = $value->attributes->totalDistance;
                        }
                    }
                }
            }
        }

        if (($session->read('_user_type') == 2) || ($session->read('_user_type') == 3)){
            $devices = $deviceTable->find('list', ['valueField' => function ($row) {
                            return $row['device_targetname'];
                        }])
                        // ->select(['Devices.id', 'Devices.device_traccarid',
                        //             'Devices.device_targetname', 'Devices.device_identifier'])
                        ->join([
                            'Deviceusers' => [
                                'table' => 'deviceusers',
                                'type' => 'LEFT',
                                'conditions' => 'Deviceusers.devices_id = Devices.id'
                            ]
                        ])
                        ->where(['Devices.device_status = 1'])
                        ->andwhere(['Deviceusers.deviceusers_startdate <= ' => date('Y-m-d H:i:s')])
                        ->andwhere(['Deviceusers.deviceusers_enddate >= ' => date('Y-m-d H:i:s')])
                        ->andwhere(['Deviceusers.users_id ' => $session->read('_user_id')])
                        ->toArray();
        }else if (($session->read('_user_type') == 1)){
            $devices = $deviceTable->find('list', ['valueField' => function ($row) {
                            return $row['device_targetname'];
                        }])
                        // ->select(['Devices.id', 'Devices.device_traccarid',
                        //             'Devices.device_targetname', 'Devices.device_identifier'])
                        // ->join([
                        //     'Deviceusers' => [
                        //         'table' => 'deviceusers',
                        //         'type' => 'LEFT',
                        //         'conditions' => 'Deviceusers.devices_id = Devices.id'
                        //     ]
                        // ])
                        ->where(['Devices.device_status = 1'])
                        // ->andwhere(['Deviceusers.deviceusers_startdate <= ' => date('Y-m-d H:i:s')])
                        // ->andwhere(['Deviceusers.deviceusers_enddate >= ' => date('Y-m-d H:i:s')])
                        ->andwhere(['Devices.created_by ' => $session->read('_user_id')])
                        ->toArray();
        }else{
            $devices = $deviceTable->find('list', ['valueField' => function ($row) {
                            return $row['device_targetname'];
                        }]);
        }

        // debug($device_info);

        $this->set(compact('devices', 'device_info', 'start_date', 'end_date', 'device_targetname'));
        $this->set('_serialize', ['devices', 'device_info', 'start_date', 'end_date', 'device_targetname']);
    }

    public function overspeed(){
        $session = $this->request->session();
        $deviceTable = TableRegistry::get('Devices');

        $device_targetname = '';
        $device_info = array();
        $device_overspeed = array();
        $start_date = '';
        $end_date = '';

        if ($this->request->is('post')) {
            // debug($this->request->data);
            $device = $deviceTable->find('all')
                        ->where(['id' => $this->request->data['devicesid']])
                        ->toArray();

            $total_distance = '';

            $obj_traccar = new \App\Utility\Traccar();
            $t_login = $obj_traccar->login();

            $device_targetname = $device[0]->device_targetname;
            // $device_id = $this->request->data['devicesid'];
            $device_id = $device[0]->device_traccarid;
            $start_date = $this->request->data['startdate'];
            $end_date = $this->request->data['enddate'];

            $from_date = date('Y-m-d H:i:s', strtotime('-330 minutes', strtotime($start_date)));
            $to_date = date('Y-m-d H:i:s', strtotime('-330 minutes', strtotime($end_date)));

            $from_date = date('c', strtotime(date($from_date)));
            $to_date = date('c', strtotime(date($to_date)));

            $from_date = substr($from_date, 0, strpos($from_date, '+')).'Z';
            $to_date = substr($to_date, 0, strpos($to_date, '+')).'Z';

            if ($t_login->responseCode == 200){
                $t_devices = $obj_traccar->positions($device_id, $from_date, $to_date);
                $device_init_info = json_decode($t_devices->response);
                // debug($device_init_info);
                $init_time = '';
                $avg_speed = 0;
                $entry_cnt = 0;
                $latlong = '';

                foreach ($device_init_info as $key => $value) {

                    // $device[0]->device_overspeed
                    // $device[0]->device_overspeedduration
                    if ($value->speed >= $device[0]->device_overspeed){
                        if ($init_time == ''){
                            $init_time = date('Y-m-d H:i:s', strtotime($value->fixTime));

                            $device_overspeed[0] = ['date' => date('d-M-Y', strtotime($init_time)),
                                                        'time' => date('H:i', strtotime($init_time)),
                                                        'latlong' => $value->latitude.', '.$value->longitude,
                                                        'address' => '',
                                                        'status' => $value->attributes->motion,
                                                        'speed' => round($value->speed, 2),
                                                        'distance' => 0,
                                                        'id' => $value->id,
                                                        'fixTime' => $value->fixTime,
                                                        'overspeedlimit' => $device[0]->device_overspeed];
                            $avg_speed += round($value->speed, 2);
                            $entry_cnt++;

                            $totalDistance = $value->attributes->totalDistance;
                        }else{

                            if (($device[0]->device_overspeedduration == 0) ||
                                ($device[0]->device_overspeedduration == '')){
                                $osduration = 120;
                            }else{
                                $osduration = $device[0]->device_overspeedduration;
                            }

                            if ((strtotime(date('Y-m-d H:i:s', strtotime($value->fixTime))) -
                                strtotime($init_time)) >= $osduration){

                                $device_overspeed[1] = ['date' => date('d-M-Y', strtotime($init_time)),
                                                            'time' => date('H:i', strtotime($init_time)),
                                                            'latlong' => $value->latitude.', '.$value->longitude,
                                                            'address' => '',
                                                            'status' => $value->attributes->motion,
                                                            'speed' => round($value->speed, 2),
                                                            'distance' => $value->attributes->totalDistance - $totalDistance,
                                                            'id' => $value->id,
                                                            'fixTime' => $value->fixTime,
                                                            'overspeedlimit' => $device[0]->device_overspeed];
                                // $totalDistance = $value->attributes->totalDistance;
                                $avg_speed += round($value->speed, 2);
                                $entry_cnt++;
                            }
                        }
                        // debug($value);
                        // debug($device_overspeed);
                    }else{

                        if ((!empty($device_overspeed[0])) && (!empty($device_overspeed[1]))){
                            $avg_speed = $avg_speed / $entry_cnt;
                            $device_overspeed[1]['speed'] = $avg_speed;

                            $latlong = explode(', ', $device_overspeed[0]['latlong']);
                            $device_overspeed[0]['address'] = self::get_address($latlong[0], $latlong[1]);

                            $latlong = explode(', ', $device_overspeed[1]['latlong']);
                            $device_overspeed[1]['address'] = self::get_address($latlong[0], $latlong[1]);

                            $device_info[] = $device_overspeed;
                        }

                        $device_overspeed = null;
                        $init_time = '';
                        $entry_cnt = 0;
                        // debug($value->fixTime);
                        // debug($value->speed);
                    }
                    // debug($device_info);
                }
            }

            // debug($device_overspeed);
        }

        if (($session->read('_user_type') == 2) || ($session->read('_user_type') == 3)){
            $devices = $deviceTable->find('list', ['valueField' => function ($row) {
                            return $row['device_targetname'];
                        }])
                        // ->select(['Devices.id', 'Devices.device_traccarid',
                        //             'Devices.device_targetname', 'Devices.device_identifier'])
                        ->join([
                            'Deviceusers' => [
                                'table' => 'deviceusers',
                                'type' => 'LEFT',
                                'conditions' => 'Deviceusers.devices_id = Devices.id'
                            ]
                        ])
                        ->where(['Devices.device_status = 1'])
                        ->andwhere(['Deviceusers.deviceusers_startdate <= ' => date('Y-m-d H:i:s')])
                        ->andwhere(['Deviceusers.deviceusers_enddate >= ' => date('Y-m-d H:i:s')])
                        ->andwhere(['Deviceusers.users_id ' => $session->read('_user_id')])
                        ->toArray();
        }else if (($session->read('_user_type') == 1)){
            $devices = $deviceTable->find('list', ['valueField' => function ($row) {
                            return $row['device_targetname'];
                        }])
                        // ->select(['Devices.id', 'Devices.device_traccarid',
                        //             'Devices.device_targetname', 'Devices.device_identifier'])
                        // ->join([
                        //     'Deviceusers' => [
                        //         'table' => 'deviceusers',
                        //         'type' => 'LEFT',
                        //         'conditions' => 'Deviceusers.devices_id = Devices.id'
                        //     ]
                        // ])
                        ->where(['Devices.device_status = 1'])
                        // ->andwhere(['Deviceusers.deviceusers_startdate <= ' => date('Y-m-d H:i:s')])
                        // ->andwhere(['Deviceusers.deviceusers_enddate >= ' => date('Y-m-d H:i:s')])
                        ->andwhere(['Devices.created_by ' => $session->read('_user_id')])
                        ->toArray();
        }else {
            $devices = $deviceTable->find('list', ['limit' => 200]);
        }

        $this->set(compact('devices', 'device_info', 'start_date', 'end_date', 'device_targetname'));
        $this->set('_serialize', ['devices', 'device_info', 'start_date', 'end_date', 'device_targetname']);
    }

    public function gtid(){
        $session = $this->request->session();
        $deviceTable = TableRegistry::get('Devices');
        $devices = array();

        if (($session->read('_user_type') == 1)){
            $devices = $deviceTable->find('all')
                        ->select(['Devices.id', 'Devices.device_targetid', 'Devices.device_targetname',
                            'Devices.device_model', 'Users.user_name', 'Users.user_shortname',
                            'Users.user_fname', 'Users.user_lname', 'Users.user_cellphone',
                            'Users.user_address', 'Users.user_city', 'States.state_name'])
                        // ->join([
                        //     'Deviceusers' => [
                        //         'table' => 'deviceusers',
                        //         'type' => 'LEFT',
                        //         'conditions' => 'Devices.id = Deviceusers.devices_id'
                        //     ]
                        // ])
                        ->join([
                            'Users' => [
                                'table' => 'users',
                                'type' => 'LEFT',
                                'conditions' => 'Users.id = '.$session->read('_user_id')
                            ]
                        ])
                        ->join([
                            'States' => [
                                'table' => 'states',
                                'type' => 'LEFT',
                                'conditions' => 'Users.states_id = States.id'
                            ]
                        ])
                        // ->where(['Deviceusers.deviceusers_startdate <= ' => date('Y-m-d H:i:s')])
                        // ->andwhere(['Deviceusers.deviceusers_enddate >= ' => date('Y-m-d H:i:s')])
                        ->andwhere(['Devices.created_by ' => $session->read('_user_id')])
                        ->order(['Devices.id' => 'ASC'])
                        ->toArray();
        }

        $this->set(compact('devices'));
        $this->set('_serialize', ['devices']);
    }

    public function mid(){
        $session = $this->request->session();
        $deviceTable = TableRegistry::get('Devices');
        $devices = array();

        if (($session->read('_user_type') == 1)){
            $devices = $deviceTable->find('all')
                        ->select(['Devices.id', 'Devices.device_targetid', 'Devices.device_targetname',
                            'Devices.device_model', 'Users.user_name', 'Users.user_shortname',
                            'Users.user_fname', 'Users.user_lname', 'Users.user_cellphone',
                            'Users.user_address', 'Users.user_city', 'States.state_name'])
                        // ->join([
                        //     'Deviceusers' => [
                        //         'table' => 'deviceusers',
                        //         'type' => 'LEFT',
                        //         'conditions' => 'Devices.id = Deviceusers.devices_id'
                        //     ]
                        // ])
                        ->join([
                            'Users' => [
                                'table' => 'users',
                                'type' => 'LEFT',
                                'conditions' => 'Users.id = '.$session->read('_user_id')
                            ]
                        ])
                        ->join([
                            'States' => [
                                'table' => 'states',
                                'type' => 'LEFT',
                                'conditions' => 'Users.states_id = States.id'
                            ]
                        ])
                        // ->where(['Deviceusers.deviceusers_startdate <= ' => date('Y-m-d H:i:s')])
                        // ->andwhere(['Deviceusers.deviceusers_enddate >= ' => date('Y-m-d H:i:s')])
                        ->andwhere(['Devices.created_by ' => $session->read('_user_id')])
                        ->order(['Devices.id' => 'ASC'])
                        ->toArray();
        }

        $this->set(compact('devices'));
        $this->set('_serialize', ['devices']);
    }

    public function ismd(){
        $session = $this->request->session();
        $deviceTable = TableRegistry::get('Devices');

        $device_targetname = '';
        $device_info = array();
        $tmp_device_info = array();
        $start_date = '';
        $end_date = '';
        $avg_speed = 0;
        $entry_cnt = 0;

        if ($this->request->is('post')) {
            // debug($this->request->data);
            $device = $deviceTable->find('all')
                        ->where(['id' => $this->request->data['devicesid']])
                        ->toArray();

            $total_distance = '';

            $obj_traccar = new \App\Utility\Traccar();
            $t_login = $obj_traccar->login();

            $positionTable = TableRegistry::get('TcPositions');

            $device_targetname = $device[0]->device_targetname;
            // $device_id = $this->request->data['devicesid'];
            $device_id = $device[0]->device_traccarid;
            $start_date = $this->request->data['startdate'];
            $end_date = $this->request->data['enddate'];

            $from_date = date('Y-m-d H:i:s', strtotime('-330 minutes', strtotime($start_date)));
            $to_date = date('Y-m-d H:i:s', strtotime('-330 minutes', strtotime($end_date)));

            $from_date = date('c', strtotime(date($from_date)));
            $to_date = date('c', strtotime(date($to_date)));

            $from_date = substr($from_date, 0, strpos($from_date, '+')).'Z';
            $to_date = substr($to_date, 0, strpos($to_date, '+')).'Z';

            if ($t_login->responseCode == 200){
                $t_devices = $obj_traccar->positions($device_id, $from_date, $to_date);
                $device_init_info = json_decode($t_devices->response);

                $init_time = '';
                $init_status = '';
                $_arr_reset = false;

                foreach ($device_init_info as $key => $value) {
                    // debug($value);
                    if ($init_status == ''){
                        $init_status = ($value->attributes->motion)?'moving':'stopped';
                        $tmp_device_info[0] = ['date' => date('d-M-Y H:i:s', strtotime($value->fixTime)),
                                                    'time' => '',
                                                    'latlong' => $value->latitude.', '.$value->longitude,
                                                    'address' => '', //self::get_address($value->latitude, $value->longitude),
                                                    'status' => ($value->attributes->motion)?'moving':'stopped',
                                                    'speed' => round($value->speed, 2),
                                                    'distance' => $value->attributes->totalDistance,
                                                    'pos_id' => $value->id];

                        $avg_speed = round($value->speed, 2);
                        $entry_cnt++;
                    }else{
                        // debug('else');
                        // debug('[0]'.$tmp_device_info[0]['distance']);
                        // debug('[1]'.$value->attributes->totalDistance);
                        if ($init_status == (($value->attributes->motion)?'moving':'stopped')){
                            $tmp_device_info[1] = ['date' => date('d-M-Y H:i:s', strtotime($value->fixTime)),
                                                        'time' => '',
                                                        'latlong' => $value->latitude.', '.$value->longitude,
                                                        'address' => '',
                                                        'status' => ($value->attributes->motion)?'moving':'stopped',
                                                        'speed' => round($value->speed, 2),
                                                        'distance' => $value->attributes->totalDistance - $tmp_device_info[0]['distance'],
                                                        'pos_id' => $value->id];
                            // $totalDistance = $value->attributes->totalDistance;
                            $avg_speed += round($value->speed, 2);
                            $entry_cnt++;
                        }else{
                            if (!empty($tmp_device_info[1])){
                                // if ($tmp_device_info[0]['status'] == 'stopped'){
                                if (!$value->attributes->motion){
                                    debug($tmp_device_info[1]['date'].' -> '.$tmp_device_info[0]['date']);
                                    debug(strtotime($tmp_device_info[1]['date']) - strtotime($tmp_device_info[0]['date']));
                                    $_tdiff = (strtotime($tmp_device_info[1]['date']) - strtotime($tmp_device_info[0]['date']));
                                    if ($_tdiff >= 120){
                                        debug('stop sub');
                                        // strtotime($tmp_device_info[0]['date']))/16) >=120 ){
                                            $tmp_device_info[1]['speed'] = round(($avg_speed / $entry_cnt), 2);
                                            $tmp_ltlng = explode(', ',$tmp_device_info[1]['latlong']);
                                            $tmp_device_info[1]['address'] = self::get_address($tmp_ltlng[0], $tmp_ltlng[1]);
                                            array_push($device_info, $tmp_device_info);
                                            $_arr_reset = true;
                                    }
                                }else{
                                    $tmp_ltlng = explode(', ',$tmp_device_info[0]['latlong']);
                                    $tmp_device_info[0]['address'] = self::get_address($tmp_ltlng[0], $tmp_ltlng[1]);
                                    $tmp_ltlng = explode(', ',$tmp_device_info[1]['latlong']);
                                    $tmp_device_info[1]['address'] = self::get_address($tmp_ltlng[0], $tmp_ltlng[1]);

                                    $tmp_device_info[1]['speed'] = round(($avg_speed / $entry_cnt), 2);

                                    if (count($device_info) != 0){
                                        if ($device_info[(count($device_info) - 1)][0]['status'] == 'stopped'){
                                            array_push($device_info, $tmp_device_info);
                                            $_arr_reset = true;
                                        }else{
                                            $device_info[(count($device_info) - 1)][1] = [
                                                                        'date' => $tmp_device_info[1]['date'],
                                                                        'time' => '',
                                                                        'latlong' => $tmp_device_info[1]['latlong'],
                                                                        'address' => $tmp_device_info[1]['address'],
                                                                        'status' => $tmp_device_info[1]['status'],
                                                                        'speed' => round((($device_info[(count($device_info) - 1)][1]['speed'] + $tmp_device_info[1]['speed']) / 2), 2),
                                                                        'distance' => $tmp_device_info[1]['distance'], //+ $tmp_device_info[1]['distance'],
                                                                        'pos_id' => $tmp_device_info[1]['pos_id']
                                                                    ];
                                        }
                                    }else{
                                        array_push($device_info, $tmp_device_info);
                                        $_arr_reset = true;
                                    }
                                }
                            }
                            // $device_info[] = $tmp_device_info;
                            if ($_arr_reset){
                                unset($tmp_device_info);
                                $tmp_device_info = array();
                                $init_status = ($value->attributes->motion)?'moving':'stopped';

                                $tmp_device_info[0] = ['date' => date('d-M-Y H:i:s', strtotime($value->fixTime)),
                                                            'time' => '',
                                                            'latlong' => $value->latitude.', '.$value->longitude,
                                                            'address' => '', //self::get_address($value->latitude, $value->longitude),
                                                            'status' => ($value->attributes->motion)?'moving':'stopped',
                                                            'speed' => round($value->speed, 2),
                                                            'distance' => $value->attributes->totalDistance,
                                                            'pos_id' => $value->id];

                                $totalDistance = $value->attributes->totalDistance;
                                $avg_speed = round($value->speed, 2);
                                $entry_cnt = 1;
                                $_arr_reset = false;
                            }
                        }
                    }
                }

                // debug($device_info);
            }
        }

        if (($session->read('_user_type') == 2) || ($session->read('_user_type') == 3)){
            $devices = $deviceTable->find('list', ['valueField' => function ($row) {
                            return $row['device_targetname'];
                        }])
                        ->join([
                            'Deviceusers' => [
                                'table' => 'deviceusers',
                                'type' => 'LEFT',
                                'conditions' => 'Deviceusers.devices_id = Devices.id'
                            ]
                        ])
                        ->where(['Devices.device_status = 1'])
                        ->andwhere(['Deviceusers.deviceusers_startdate <= ' => date('Y-m-d H:i:s')])
                        ->andwhere(['Deviceusers.deviceusers_enddate >= ' => date('Y-m-d H:i:s')])
                        ->andwhere(['Deviceusers.users_id ' => $session->read('_user_id')])
                        ->toArray();
        }else if (($session->read('_user_type') == 1)){
            $devices = $deviceTable->find('list', ['valueField' => function ($row) {
                            return $row['device_targetname'];
                        }]);
        }

        $this->set(compact('devices', 'device_info', 'start_date', 'end_date', 'device_targetname'));
        $this->set('_serialize', ['devices', 'device_info', 'start_date', 'end_date', 'device_targetname']);
    }

    public function gpstop(){
        $session = $this->request->session();
        $deviceTable = TableRegistry::get('Devices');

        $device_targetname = '';
        $device_info = array();
        $device_stops = array();
        $start_date = '';
        $end_date = '';
        $device_info_cnt = 0;

        if ($this->request->is('post')) {
            if (($session->read('_user_type') != 5)){
                $devices = $deviceTable->find('all')
                            ->select(['Devices.id', 'Devices.device_traccarid', 'Devices.device_targetid',
                                        'Devices.device_targetname', 'Devices.device_identifier',
                                        'Devices.device_stoppagetime'])
                            // ->join([
                            //     'Deviceusers' => [
                            //         'table' => 'deviceusers',
                            //         'type' => 'LEFT',
                            //         'conditions' => 'Deviceusers.devices_id = Devices.id'
                            //     ]
                            // ])
                            ->where(['Devices.device_status = 1'])
                            // ->andwhere(['Deviceusers.deviceusers_startdate <= ' => date('Y-m-d H:i:s')])
                            // ->andwhere(['Deviceusers.deviceusers_enddate >= ' => date('Y-m-d H:i:s')])
                            ->andwhere(['Devices.created_by ' => $session->read('_user_id')])
                            ->toArray();
            }else{
                $devices = $deviceTable->find('all', ['limit' => 200]);
            }

            $obj_traccar = new \App\Utility\Traccar();
            $t_login = $obj_traccar->login();

            $start_date = $this->request->data['startdate'];
            $end_date = $this->request->data['enddate'];

            $from_date = date('Y-m-d H:i:s', strtotime('-330 minutes', strtotime($start_date)));
            $to_date = date('Y-m-d H:i:s', strtotime('-330 minutes', strtotime($end_date)));

            $from_date = date('c', strtotime(date($from_date)));
            $to_date = date('c', strtotime(date($to_date)));

            $from_date = substr($from_date, 0, strpos($from_date, '+')).'Z';
            $to_date = substr($to_date, 0, strpos($to_date, '+')).'Z';

            if ($t_login->responseCode == 200){

                foreach ($devices as $key => $value) {
                    $device_targetname = $value->device_targetname;
                    $device_targetid = $value->device_targetid;
                    $device_stoppagetime = (($value->device_stoppagetime == '')?120:$value->device_stoppagetime);
                    $device_id = $value->device_traccarid;

                    $t_devices = $obj_traccar->positions($device_id, $from_date, $to_date);
                    $device_init_info = json_decode($t_devices->response);

                    foreach ($device_init_info as $_key => $posvalue) {
                        // debug($posvalue->attributes->motion);
                        if (!$posvalue->attributes->motion){
                            if (empty($device_stops)){
                                $device_stops[0] = ['date' => date('d-M-Y H:i:s', strtotime($posvalue->fixTime)),
                                                            'gtname' => $device_targetname,
                                                            'gtid' => $device_targetid,
                                                            'latlong' => $posvalue->latitude.', '.$posvalue->longitude,
                                                            'address' => '',
                                                            'status' => $posvalue->attributes->motion,
                                                            'stoppagetime' => $device_stoppagetime,
                                                            'speed' => round($posvalue->speed, 2),
                                                            'distance' => 0,
                                                            'duration' => 0,
                                                            'id' => $posvalue->id,
                                                            'fixTime' => $posvalue->fixTime];
                            }else{
                                $device_stops[1] = ['date' => date('d-M-Y H:i:s', strtotime($posvalue->fixTime)),
                                                            'gtname' => $device_targetname,
                                                            'gtid' => $device_targetid,
                                                            'latlong' => $posvalue->latitude.', '.$posvalue->longitude,
                                                            'address' => '',
                                                            'status' => $posvalue->attributes->motion,
                                                            'stoppagetime' => $device_stoppagetime,
                                                            'speed' => round($posvalue->speed, 2),
                                                            'distance' => 0,
                                                            'duration' => 0,
                                                            'id' => $posvalue->id,
                                                            'fixTime' => $posvalue->fixTime];
                            }
                        }else{
                            if (!empty($device_stops[1])){
                                $_diff = strtotime($device_stops[1]['date']) - strtotime($device_stops[0]['date']);

                                // debug($device_targetname.' - '.$device_stoppagetime);
                                // debug($device_stops[1]['date'].' - '.$device_stops[0]['date']);
                                // debug($_diff);
                                // debug('stoppagetime - '.gmdate("H:i:s", $_diff));

                                // if (date('i', $_diff) >= $device_stoppagetime){
                                if ($_diff >= ($device_stoppagetime * 60)){
                                    $tmp_ltlng = explode(', ',$device_stops[1]['latlong']);
                                    // $device_stops[1]['address'] = self::get_address($tmp_ltlng[0], $tmp_ltlng[1]);
                                    $device_stops[1]['address'] = '';
                                    $device_stops[1]['duration'] = $_diff;
                                    array_push($device_info, $device_stops);
                                }
                                // $device_info[$device_info_cnt] = $device_stops;
                                // $device_info_cnt++;
                            }
                            // debug('device info count - '.count($device_info));
                            // debug('device stop count - '.count($device_stops));
                            unset($device_stops);
                        }
                    }
                    unset($device_stops);
                    $device_init_info = '';
                }
                // debug($device_info);
            }
        }

        $this->set(compact('devices', 'device_info', 'start_date', 'end_date', 'device_targetname'));
        $this->set('_serialize', ['devices', 'device_info', 'start_date', 'end_date', 'device_targetname']);
    }

    public function pboverview(){

        $session = $this->request->session();
        $deviceTable = TableRegistry::get('Devices');
        $device_init_info = $start_date = $end_date = '';
        $device_id = 0;
        $device_init_info = '';
        $device_targetname = '';

        $obj_traccar = new \App\Utility\Traccar();
        $t_login = $obj_traccar->login();

        if ($this->request->is('post')) {

            if (isset($this->request->data['startdate'])){
                $start_date = $this->request->data['startdate'];
                $end_date = $this->request->data['enddate'];
            }else{
                $start_date = date('Y-m-d').' 00:00';
                $end_date = date('Y-m-d').' 23:59';
            }

            if (($session->read('_user_type') == 2) || ($session->read('_user_type') == 3)){
                $devices = $deviceTable->find('all', [])
                        ->join([
                            'Deviceusers' => [
                                'table' => 'deviceusers',
                                'type' => 'LEFT',
                                'conditions' => 'Deviceusers.devices_id = Devices.id'
                            ]
                        ])
                        ->where(['Devices.device_status = 1'])
                        ->andwhere(['Deviceusers.deviceusers_startdate <= "'.$start_date.'"'])
                        ->andwhere(['Deviceusers.deviceusers_enddate >= "'.$end_date.'"'])
                        ->andwhere(['Deviceusers.users_id ' => $session->read('_user_id')])
                        ->andwhere(['Devices.device_targetname' => $session->read('_device_targetname')])
                        ->toArray();
            }else{
                $devices = $deviceTable->find('all', [])
                        ->select(['Devices.id', 'Devices.device_traccarid',
                                    'Devices.device_targetname', 'Devices.device_identifier'])
                        ->where(['Devices.device_status = 1'])
                        ->andwhere(['Devices.created_by ' => $session->read('_user_id')])
                        ->toArray();
                        // ->where(['Devices.device_status = 1'])
                        // ->andwhere(['Devices.device_targetname' => $session->read('_device_targetname')])
                        // ->toArray();
            }

            if (!empty($devices)){
                $device_id = $devices[0]['device_traccarid'];
                $device_targetname = $devices[0]['device_targetname'];

                $from_date = date('Y-m-d H:i:s', strtotime('-330 minutes', strtotime($start_date)));
                $to_date = date('Y-m-d H:i:s', strtotime('-330 minutes', strtotime($end_date)));

                $from_date = date('c', strtotime(date($from_date)));
                $to_date = date('c', strtotime(date($to_date)));

                $from_date = substr($from_date, 0, strpos($from_date, '+')).'Z';
                $to_date = substr($to_date, 0, strpos($to_date, '+')).'Z';

                if ($t_login->responseCode == 200){
                    $t_devices = $obj_traccar->positions($device_id, $from_date, $to_date);
                    $device_init_info = json_decode($t_devices->response);

                    if (empty($device_init_info)){
                        $this->Flash->error(__('No location data found.', 'Device'));
                        $device_init_info = 0;
                    }else{
                        $device_init_info = json_encode($device_init_info);
                    }
                }else{
                }
            }else{
                $device_init_info = 0;
                $this->Flash->error(__('You don\'t have access to the device, between the selected date/time range.', 'Device'));
            }
        }

        if (($session->read('_user_type') == 2) || ($session->read('_user_type') == 3)){
            $devices = $deviceTable->find('list', ['valueField' => function ($row) {
                            return $row['device_targetname'];
                        }])
                        ->join([
                            'Deviceusers' => [
                                'table' => 'deviceusers',
                                'type' => 'LEFT',
                                'conditions' => 'Deviceusers.devices_id = Devices.id'
                            ]
                        ])
                        ->where(['Devices.device_status = 1'])
                        ->andwhere(['Deviceusers.deviceusers_startdate <= ' => date('Y-m-d H:i:s')])
                        ->andwhere(['Deviceusers.deviceusers_enddate >= ' => date('Y-m-d H:i:s')])
                        ->andwhere(['Deviceusers.users_id ' => $session->read('_user_id')])
                        ->toArray();
        }else if (($session->read('_user_type') == 1)){
            $devices = $deviceTable->find('list', ['valueField' => function ($row) {
                            return $row['device_targetname'];
                        }])
                        ->select(['Devices.id', 'Devices.device_traccarid',
                                    'Devices.device_targetname', 'Devices.device_identifier'])
                        ->where(['Devices.device_status = 1'])
                        ->andwhere(['Devices.created_by ' => $session->read('_user_id')])
                        ->toArray();
        }else{
            $devices = $deviceTable->find('list',[]);
        }

        if ($start_date == ''){
            $start_date = $end_date = date('Y-m-d H:i:s');
        }
        $this->set(compact('device_id', 'device_targetname', 'devices', 'device_init_info', 'start_date', 'end_date'));
    }

    public function gmileage(){
        $session = $this->request->session();
        $deviceTable = TableRegistry::get('Devices');

        $device_targetname = '';
        $device_info = array();
        $device_stops = array();
        $device_overspeed = array();
        $tmp_device_info = array('device_targetname' => '', 'device_targetid' => '', 'device_stoppagetime' => '', 'device_mileage' => '', 'device_overspeed' => '',  'device_overspeedduration' => '',
                            'device_id' => 0, 'start_distance' => 0, 'end_distance' => 0, 'overspeed' => 0, 'stops' => 0, 'acc' => 0);
        $start_date = '';
        $end_date = '';
        $init_time = '';
        $device_info_cnt = 0;

        if ($this->request->is('post')) {

            if (($session->read('_user_type') == 2) || ($session->read('_user_type') == 3)){
                $devices = $deviceTable->find('all', [])
                        // ->select(['Devices.id', 'Devices.device_traccarid', 'Devices.device_targetid',
                        //     'Devices.device_targetname', 'Devices.device_identifier',
                        //     'Devices.device_milage', 'Devices.device_stoppagetime'])
                        ->join([
                            'Deviceusers' => [
                                'table' => 'deviceusers',
                                'type' => 'LEFT',
                                'conditions' => 'Deviceusers.devices_id = Devices.id'
                            ]
                        ])
                        ->where(['Devices.device_status = 1'])
                        ->andwhere(['Deviceusers.deviceusers_startdate <= "'.$this->request->data['startdate'].'"'])
                        ->andwhere(['Deviceusers.deviceusers_enddate >= "'.$this->request->data['enddate'].'"'])
                        ->andwhere(['Deviceusers.users_id ' => $session->read('_user_id')])
                        // ->andwhere(['Devices.device_targetname' => $session->read('_device_targetname')])
                        ->toArray();
            }else{
                $devices = $deviceTable->find('all', [])
                        // ->select(['Devices.id', 'Devices.device_traccarid', 'Devices.device_targetid',
                        //             'Devices.device_targetname', 'Devices.device_identifier',
                        //             'Devices.device_milage', 'Devices.device_stoppagetime'])
                        ->where(['Devices.device_status = 1'])
                        ->andwhere(['Devices.created_by ' => $session->read('_user_id')])
                        ->toArray();
            }

            $obj_traccar = new \App\Utility\Traccar();
            $t_login = $obj_traccar->login();

            $start_date = $this->request->data['startdate'];
            $end_date = $this->request->data['enddate'];

            $from_date = date('Y-m-d H:i:s', strtotime('-330 minutes', strtotime($start_date)));
            $to_date = date('Y-m-d H:i:s', strtotime('-330 minutes', strtotime($end_date)));

            $from_date = date('c', strtotime(date($from_date)));
            $to_date = date('c', strtotime(date($to_date)));

            $from_date = substr($from_date, 0, strpos($from_date, '+')).'Z';
            $to_date = substr($to_date, 0, strpos($to_date, '+')).'Z';

            if ($t_login->responseCode == 200){

                foreach ($devices as $key => $value) {
                    // debug($value);
                    $tmp_device_info = array('device_targetname' => '', 'device_targetid' => '', 'device_stoppagetime' => '', 'device_mileage' => '', 'device_overspeed' => '',  'device_overspeedduration' => '',
                                        'device_id' => 0, 'start_distance' => 0, 'end_distance' => 0, 'overspeed' => 0, 'stops' => 0, 'acc' => 0);

                    $tmp_device_info['device_targetname'] = $value->device_targetname;
                    $tmp_device_info['device_targetid'] = $value->device_targetid;
                    $tmp_device_info['device_stoppagetime'] = $value->device_stoppagetime;
                    $tmp_device_info['device_mileage'] = $value->device_milage;
                    $tmp_device_info['device_overspeed'] = $value->device_overspeed;
                    $tmp_device_info['device_overspeedduration'] = $value->device_overspeedduration;
                    $tmp_device_info['device_id'] = $value->device_traccarid;
                    $tmp_device_info['start_distance'] = 0;
                    $tmp_device_info['end_distance'] = 0;
                    $tmp_device_info['overspeed'] = 0;
                    $tmp_device_info['stops'] = 0;
                    $tmp_device_info['acc'] = 0;
                    $acc_stat = $tmp_acc_stat = '';

                    $t_devices = $obj_traccar->lcpositions($value->device_traccarid, $from_date, $to_date);
                    // $device_init_info = json_decode($t_devices->response);
                    $device_init_info = json_decode($t_devices);

                    foreach ($device_init_info as $_key => $posvalue) {
                        // debug($posvalue);
                        $posvalue->attributes = json_decode($posvalue->attributes);
                        if ($tmp_device_info['start_distance'] == 0){
                            $tmp_device_info['start_distance'] = $posvalue->attributes->totalDistance;
                        }
                        $tmp_device_info['end_distance'] = $posvalue->attributes->totalDistance;

                        $tmp_acc_stat = (isset($posvalue->attributes->ignition)?'F':'T');

                        if ($acc_stat == ''){
                            $acc_stat = $tmp_acc_stat;
                        }else if ($acc_stat != $tmp_acc_stat){
                            $tmp_device_info['acc']++;
                            $acc_stat = $tmp_acc_stat;
                        }

                        if ($posvalue->speed >= $tmp_device_info['device_overspeed']){
                            // debug('OS-in '.$posvalue->speed);
                            if (empty($device_overspeed[0])){
                                $device_overspeed[0] = ['date' => date('Y-m-d H:i:s', strtotime($posvalue->fixtime))];
                            }else {
                                $device_overspeed[1] = ['date' => date('Y-m-d H:i:s', strtotime($posvalue->fixtime))];
                            }
                        }else{
                            // debug('OS-out '.$tmp_device_info['device_overspeedduration']);
                            if ((!empty($device_overspeed[0])) && (!empty($device_overspeed[1]))){
                                // debug('OS-out - 1');
                                // debug($device_overspeed[1]['date'].' -- '.$device_overspeed[0]['date']);
                                // debug((strtotime(date('Y-m-d H:i:s', strtotime($device_overspeed[1]['date']))) -
                                    // strtotime($device_overspeed[0]['date'])));
                                if ((strtotime(date('Y-m-d H:i:s', strtotime($device_overspeed[1]['date']))) -
                                    strtotime($device_overspeed[0]['date'])) >= $tmp_device_info['device_overspeedduration']){
                                        // debug('OS-out - 1.1');
                                        $tmp_device_info['overspeed']++;
                                }
                            }
                            unset($device_overspeed);
                        }

                        if ($posvalue->attributes->motion){
                            if ((!empty($device_stops[0])) && (!empty($device_stops[1]))){

                                if (($tmp_device_info['device_stoppagetime'] != '') &&
                                    ($tmp_device_info['device_stoppagetime'] != 0)){
                                    $device_stoppagetime = $tmp_device_info['device_stoppagetime'] * 60;
                                }else{
                                    $device_stoppagetime = 120 * 60;
                                }
                                if ((strtotime(date('Y-m-d H:i:s', strtotime($device_stops[1]['date']))) -
                                    strtotime($device_stops[0]['date'])) >= $device_stoppagetime){
                                        // debug((strtotime(date('Y-m-d H:i:s', strtotime($device_stops[1]['date']))) -
                                        //     strtotime($device_stops[0]['date'])));
                                        $tmp_device_info['stops']++;
                                }
                            }
                            unset($device_stops);
                        }else{
                            // debug('STP-in');
                            if (empty($device_stops[0])){
                                $device_stops[0] = ['date' => date('Y-m-d H:i:s', strtotime($posvalue->fixtime))];
                            }else {
                                $device_stops[1] = ['date' => date('Y-m-d H:i:s', strtotime($posvalue->fixtime))];
                            }
                        }

                    }

                    array_push($device_info, $tmp_device_info);

                    unset($tmp_device_info);
                    $tmp_device_info = '';
                }
                // debug($device_info);
            }
        }

        $this->set(compact('devices', 'device_info', 'start_date', 'end_date', 'device_targetname'));
        $this->set('_serialize', ['devices', 'device_info', 'start_date', 'end_date', 'device_targetname']);
    }

    private function get_address($lat, $long){
        $url  = "https://maps.googleapis.com/maps/api/geocode/json?latlng=".$lat.",".$long."&sensor=false&key=AIzaSyDLxUxba_DtKuafKXLMx2mnpOqGX9ZkCkA";
        $json = @file_get_contents($url);
        $address = json_decode($json);
        // debug($address->results[0]->formatted_address);
        return $address->results[0]->formatted_address;
    }

    public function listdevices(){
        $session = $this->request->session();

        $deviceTable = TableRegistry::get('Devices');

        if ($this->request->is(['post', 'put'])) {
            //
            // $result_set = $this->Devices->find('all')
            //                 ->where(['Devices.device_targetname LIKE ' => '%'.$this->request->data('searchtext').'%']);
            // $devices = $this->paginate($result_set);

        }else if ($this->request->is('get')) {
            if (($session->read('_user_type') == 2) || ($session->read('_user_type') == 3)){
                $_devices = $deviceTable->find('all')
                        ->join([
                            'Deviceusers' => [
                                'table' => 'deviceusers',
                                'type' => 'LEFT',
                                'conditions' => 'Deviceusers.devices_id = Devices.id'
                            ]
                        ])
                        ->where(['Devices.device_status = 1'])
                        ->andwhere(['Deviceusers.deviceusers_startdate <= ' => date('Y-m-d H:i:s')])
                        ->andwhere(['Deviceusers.deviceusers_enddate >= ' => date('Y-m-d H:i:s')])
                        ->andwhere(['Deviceusers.users_id ' => $session->read('_user_id')])
                        ->contain(['Devicetypes', 'Devicegroups']);

            }else{
                $_devices = $deviceTable->find('all')
                        ->where(['Devices.device_status = 1']);
            }
        }

        $obj_traccar = new \App\Utility\Traccar();
        $t_login = $obj_traccar->login();
        $t_devices[][] = '';

        if ($t_login->responseCode == 200){
            foreach ($_devices as $key => $value) {
                $_tmp_device = $obj_traccar->devices(('id='.$value['device_traccarid']));
                $tmp_device = json_decode($_tmp_device->response);
                if ($tmp_device[0] != null){

                    if ($this->request->query['_type'] == 'lowbattery'){
                        $tp_devices = $obj_traccar->position($tmp_device[0]->positionId);
                        $tp_devices = json_decode($tp_devices->response);

                        if (isset($tmp_device[0]->attributes->battery)){
                            if ($tmp_device[0]->attributes->battery < 20){
                                $t_devices[$key][0] = $tmp_device[0];
                                $t_devices[$key][1] = $tp_devices[0];
                            }
                        }else if (isset($tmp_device[0]->attributes->battery)){
                            if ($tmp_device[0]->attributes->batteryLevel < 20){
                                $t_devices[$key][0] = $tmp_device[0];
                                $t_devices[$key][1] = $tp_devices[0];
                            }
                        }
                        $t_devices[$key][2] = $value;
                    }else{
                        if ($this->request->query['_type'] == $tmp_device[0]->status){
                            $tp_devices = $obj_traccar->position($tmp_device[0]->positionId);
                            $tp_devices = json_decode($tp_devices->response);
                            $t_devices[$key][0] = $tmp_device[0];
                            $t_devices[$key][1] = $tp_devices[0];
                            $t_devices[$key][2] = $value;
                        }
                    }
                }
            //     // $tp_devices = $obj_traccar->position($device_info[0]->positionId);
            }
            // $t_devices = json_decode($obj_traccar->devices()->response, true);
        }else{
            $t_devices = '';
        }

        $devices = $this->paginate($_devices);
        $this->set(compact('devices', 't_devices'));
    }
}
