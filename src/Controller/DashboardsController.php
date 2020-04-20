<?php

namespace App\Controller;

use App\Controller\AppController;
use Cake\ORM\TableRegistry;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use App\Utility\Traccar;
// use Cake\I18n\Time;
// use Cake\I18n\Date;

/**
 * Documents Controller
 *
 * @property \App\Model\Table\DocumentsTable $Documents
 *
 * @method \App\Model\Entity\Document[] paginate($object = null, array $settings = [])
 */
class DashboardsController extends AppController
{

    var $uses = array();

    /**
     * Index method
     *
     * @return \Cake\Http\Response|void
     */
    public function index()
    {
        $session = $this->request->session();

        if (($session->read('_user_type') == 1) || ($session->read('_user_type') == 2)) {
            return $this->redirect(['action' => 'managementdashboard']);
        } elseif ($session->read('_user_type') == 3) {
            return $this->redirect(['action' => 'userdashboard']);
        }
    }

    public function listdevices()
    {
        $session = $this->request->session();

        $deviceTable = TableRegistry::get('Devices');

        if ($this->request->is(['post', 'put'])) { } else if ($this->request->is('get')) {
            if (($session->read('_user_type') == 2) || ($session->read('_user_type') == 3)) {
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
                    ->andwhere(['Deviceusers.deviceusers_status ' => 1])
                    ->contain(['Devicetypes', 'Devicegroups']);
            } else {
                $_devices = $deviceTable->find('all')
                    ->where(['Devices.device_status = 1'])
                    ->andwhere(['Devices.created_by ' => $session->read('_user_id')]);
            }
        }
        if($this->request->query['_type'] == 'all'){
            $t_devices = array();
            foreach ($_devices as $key => $value) {
                $t_devices[$key]['device_targetid'] = $value['device_targetid'];
                $t_devices[$key]['device_targetname'] = $value['device_targetname'];
                $t_devices[$key]['device_identifier'] = $value['device_identifier'];
            }           
        }elseif($this->request->query['_type'] == 'overspeed'){
            $obj_traccar = new \App\Utility\Traccar();
            $t_login = $obj_traccar->login();
            $id_string = array();
            if ($t_login->responseCode == 200) {
                $overspeed = 0;
                foreach ($_devices as $key => $value) {
                    $id_string[] = 'id='.$value['id'];
                }
                $implode_id_string = implode('&', $id_string);
                $t_devices = json_decode($obj_traccar->devices($implode_id_string)->response, true);
                    foreach ($t_devices as $t_key => $t_value) {                                            
                        $t_device_pos = json_decode($obj_traccar->position($t_value['positionId'])->response);
                        $t_value['t_device_pos'] = $t_device_pos;
                        if (isset($t_value['attributes']['speedLimit']) && (isset($t_device_pos[0]->speed))) {
                            if ($t_value['attributes']['speedLimit'] > $t_device_pos[0]->speed) {
                                $overspeed++;                        
                            }
                        }
                    }
                
            }        
        }else{
            $obj_traccar = new \App\Utility\Traccar();
            $t_login = $obj_traccar->login();
            $t_devices[][] = '';

            if ($t_login->responseCode == 200) { 
                $_device_traccaridsary = array();           
                foreach ($_devices as $key => $value) {
                    $_tmp_device = $obj_traccar->devices(('id=' . $value['device_traccarid']));
                    $tmp_device = json_decode($_tmp_device->response);            
                    if ($tmp_device[0] != null) {

                        if ($this->request->query['_type'] == 'lowbattery') {
                            $tp_devices = $obj_traccar->position($tmp_device[0]->positionId);
                            $tp_devices = json_decode($tp_devices->response);
                            if (isset($tmp_device[0]->attributes->battery)) {
                                if ($tmp_device[0]->attributes->battery < 20) {
                                    $t_devices[$key][0] = $tmp_device[0];
                                    $t_devices[$key][1] = $tp_devices[0];
                                }
                            } else if (isset($tmp_device[0]->attributes->battery)) {
                                if ($tmp_device[0]->attributes->batteryLevel < 20) {
                                    $t_devices[$key][0] = $tmp_device[0];
                                    $t_devices[$key][1] = $tp_devices[0];
                                }
                            }
                            $t_devices[$key][2] = $value;
                        } else {

                            if ($this->request->query['_type'] == $tmp_device[0]->status) {

                                $_startdate = date('Y-m-d H:i:s', strtotime('-4 days', strtotime(date('Y-m-d H:i:s'))));
                                $_startdate = date('Y-m-d H:i:s', strtotime('-330 minutes', strtotime($_startdate)));
                                $_enddate = date('Y-m-d H:i:s', strtotime('-330 minutes', strtotime(date('Y-m-d H:i:s'))));

                                $_startdate = date('c', strtotime(date($_startdate)));
                                $_enddate = date('c', strtotime(date($_enddate)));

                                $_startdate = substr($_startdate, 0, strpos($_startdate, '+')) . 'Z';
                                $_enddate = substr($_enddate, 0, strpos($_enddate, '+')) . 'Z';

                                if ($this->request->query['_type'] == 'offline') {
                                    $tp_devicepositions = $obj_traccar->positions($tmp_device[0]->id);
                                } else {
                                    $tp_devicepositions = $obj_traccar->positions($tmp_device[0]->id, $_startdate, $_enddate);
                                }

                                $tp_devicepositions = json_decode($tp_devicepositions->response);
                                if (!empty($tp_devicepositions)) {
                                    $tp_devices[0] = $tp_devicepositions[(count($tp_devicepositions) - 1)];

                                    $datetime1 = $datetime2 = $interval = '';
                                    for ($cnt = (count($tp_devicepositions) - 2); $cnt > 0; $cnt--) {
                                        // debug($tp_devicepositions[$cnt]->attributes->motion);
                                        if ((isset($tp_devicepositions[$cnt]->attributes->motion)) && (isset($tp_devices[0]->attributes->motion))
                                        ) {
                                            if (
                                                $tp_devicepositions[$cnt]->attributes->motion !=
                                                $tp_devices[0]->attributes->motion
                                            ) {
                                                $datetime1 = new \DateTime($tp_devices[0]->fixTime);
                                                $datetime2 = new \DateTime($tp_devicepositions[$cnt]->fixTime);
                                                $interval = $datetime1->diff($datetime2);
                                                $t_devices[$key][3] = $interval->format('%h') . ":" . $interval->format('%i') . ":" . $interval->format('%s');
                                                break;
                                            } else {
                                                $t_devices[$key][3] = 0;
                                            }
                                        }
                                    }

                                    $t_devices[$key][0] = $tmp_device[0];
                                    $t_devices[$key][1] = $tp_devices[0];
                                    $t_devices[$key][2] = $value;
                                }
                            }
                        }
                    }
                    //     // $tp_devices = $obj_traccar->position($device_info[0]->positionId);
                }
                // $t_devices = json_decode($obj_traccar->devices()->response, true);
            } else {
                $t_devices[] = '';
            }
        }

        $devices = $this->paginate($_devices);
        $this->set(compact('devices', 't_devices'));
    }
    
    public function listalerts()
    {
        $this->Flash->error(__('You don\'t have access to this page. Please contact administrator.', 'Device'));
    }

    public function listusers()
    {
        $session = $this->request->session();

        $userTable = TableRegistry::get('Users');

        if ($this->request->is(['post', 'put'])) { } else if ($this->request->is('get')) {

            // debug($this->request->query['_type']);

            if (($session->read('_user_type') == 1) || ($session->read('_user_type') == 5)) {
                if ($this->request->query['_type'] == 'managers') {
                    $_users = $userTable->find('all')
                        // ->join([
                        //     'Deviceusers' => [
                        //         'table' => 'deviceusers',
                        //         'type' => 'LEFT',
                        //         'conditions' => 'Deviceusers.devices_id = Devices.id'
                        //     ]
                        // ])
                        ->where(['Users.usertypes_id = 2']) // user type manager
                        ->andwhere(['Users.created_by ' => $session->read('_user_id')])
                        ->contain(['Usertypes']);
                } else if ($this->request->query['_type'] == 'users') {
                    $_users = $userTable->find('all')
                        ->where(['Users.usertypes_id = 3']) // user type manager
                        ->andwhere(['Users.created_by ' => $session->read('_user_id')])
                        ->contain(['Usertypes']);
                }
            } else if (($session->read('_user_type') == 2)) { // manager
            }
        }

        $users = $this->paginate($_users);
        $this->set(compact('users'));
    }

    public function listdeviceusers()
    {
        $session = $this->request->session();

        if ($this->request->is(['post', 'put'])) { } else if ($this->request->is('get')) {
            if (($session->read('_user_type') == 2)) { // manager
                $devicsTable = TableRegistry::get('Devices');

                $_devices = $devicsTable->find('all')
                    ->select(['Devices.id'])
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

                $deviceusersTable = TableRegistry::get('Deviceusers');

                $deviceusers = $deviceusersTable->find('all')
                    ->contain(['Devices', 'Users'])
                    ->where(['Deviceusers.devices_id IN ' => $_devices])
                    ->andwhere(['Deviceusers.deviceusers_status' => 1])
                    ->andwhere(['Deviceusers.users_id != ' => $session->read('_user_id')])
                    ->andwhere(['Deviceusers.deviceusers_startdate <= ' => date('Y-m-d H:i:s')])
                    ->andwhere(['Deviceusers.deviceusers_enddate >= ' => date('Y-m-d H:i:s')]);
                // debug($deviceusers);
            }
        }
        $deviceusers = $this->paginate($deviceusers);
        $this->set(compact('deviceusers'));
    }

    public function userdashboard()
    {
        $session = $this->request->session();
        // if ($session->read('_user_type') != 3){
        if (($session->read('_user_type') != 2) || ($session->read('_user_type') != 3)) {
            if (($session->read('_user_type') == 1) || ($session->read('_user_type') == 5)) {
                return $this->redirect(['action' => 'managementdashboard']);
            }
        }

        $device = TableRegistry::get('Devices');

        if (($session->read('_user_type') == 3) ||     //manager
            ($session->read('_user_type') == 2)
        ) {    // user
            $_devices = $device->find('all')
                ->select([
                    'Devices.id', 'Devices.device_traccarid',
                    'Devices.device_targetname', 'Devices.device_identifier'
                ])
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
                ->andwhere(['Deviceusers.deviceusers_status ' => 1])
                ->andWhere(['Deviceusers.users_id' => $session->read('_user_id')])
                ->toArray();
        }

        $ids = '';
        $devices = '';

        foreach ($_devices as $key => $val) {
            $ids .= (($ids == '') ? ('id=' . $val['device_traccarid']) : ('&id=' . $val['device_traccarid']));
            $devices .= $val['device_traccarid'] . '|' . $val['device_targetname'] . '|' . $val['device_identifier'] . ',';
        }

        $this->set(compact('devices', 'ids'));
    }

    public function managementdashboard()
    {

        $connection = ConnectionManager::get('db2');
        $results = $connection->execute('SELECT * FROM tc_devices')->fetchAll('assoc');
        // foreach($results as $shebi){
        //     echo $shebi['id'].'=>'.$shebi['name'].'<br/>';
        // }
        // exit;
        $session = $this->request->session();
        if (($session->read('_user_type') != 1) || ($session->read('_user_type') != 5)) {
            if (($session->read('_user_type') == 3) || ($session->read('_user_type') == 2)) {
                return $this->redirect(['action' => 'userdashboard']);
            }
        }

        $device = TableRegistry::get('Devices');

        if ($session->read('_user_type') == 1) {    // admin
            $_devices = $device->find('all')
                ->select([
                    'Devices.id', 'Devices.device_traccarid',
                    'Devices.device_targetname', 'Devices.device_identifier'
                ])
                ->where(['Devices.device_status = 1'])
                ->andWhere(['Devices.created_by ' => $session->read('_user_id')])
                ->toArray();
        } else if ($session->read('_user_type') == 5) {    // super admin
            $_devices = $device->find('all')
                ->select([
                    'Devices.id', 'Devices.device_traccarid',
                    'Devices.device_targetname', 'Devices.device_identifier'
                ])
                ->where(['Devices.device_status = 1'])->toArray();
        }

        $ids = '';
        $devices = '';

        foreach ($_devices as $key => $val) {
            $ids .= (($ids == '') ? ('id=' . $val['device_traccarid']) : ('&id=' . $val['device_traccarid']));
            $devices .= $val['device_traccarid'] . '|' . $val['device_targetname'] . '|' . $val['device_identifier'] . ',';
        }

        $this->set(compact('devices', 'ids'));
    }

    public function liveview()
    {
        $session = $this->request->session();

        $device = TableRegistry::get('Devices');

        if ($session->read('_user_type') == 1) {    // admin
            $_devices = $device->find('all')
                ->select([
                    'Devices.id', 'Devices.device_traccarid',
                    'Devices.device_targetname', 'Devices.device_identifier'
                ])
                ->where(['Devices.device_status = 1'])
                ->andWhere(['Devices.created_by ' => $session->read('_user_id')])
                ->toArray();
        } else if ($session->read('_user_type') == 5) {    // super admin
            $_devices = $device->find('all')
                ->select([
                    'Devices.id', 'Devices.device_traccarid',
                    'Devices.device_targetname', 'Devices.device_identifier'
                ])
                ->where(['Devices.device_status = 1'])->toArray();
        } else if (($session->read('_user_type') == 3) ||     //manager
            ($session->read('_user_type') == 2)
        ) {    // user
            $_devices = $device->find('all')
                ->select([
                    'Devices.id', 'Devices.device_traccarid',
                    'Devices.device_targetname', 'Devices.device_identifier'
                ])
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
                ->andwhere(['Deviceusers.deviceusers_status ' => 1])
                ->andWhere(['Deviceusers.users_id' => $session->read('_user_id')])
                ->toArray();
        }

        $ids = '';
        $devices = '';

        foreach ($_devices as $key => $val) {
            $ids .= (($ids == '') ? ('id=' . $val['device_traccarid']) : ('&id=' . $val['device_traccarid']));
            $devices .= $val['device_traccarid'] . '|' . $val['device_targetname'] . '|' . $val['device_identifier'] . ',';
        }

        $this->set(compact('devices', 'ids'));
    }

    public function totaldevices()
    { }

    public function ajaxtileinfo()
    {
        $session = $this->request->session();
        $ids = $this->request->data['ids'];
        $obj_traccar = new \App\Utility\Traccar();
        $t_login = $obj_traccar->login();

        $tile_arr = array(
            'total_device' => 0,
            'online_devices' => 0,
            'offline_devices' => 0,
            'unknown_device_status' => 0,
            'low_battery' => 0,
            'overspeed' => 0,
            'managers' => 0,
            'users' => 0
        );
        $batteryLevel = 0;

        if ($ids != '') {
            if ($t_login->responseCode == 200) {
                $t_devices = json_decode($obj_traccar->devices($ids)->response, true);
                $tile_arr['total_device'] = count($t_devices);

                foreach ($t_devices as $key => $value) {
                    if ($value['status'] == 'online') {
                        $tile_arr['online_devices']++;
                    } else if ($value['status'] == 'offline') {
                        $tile_arr['offline_devices']++;
                    } else if ($value['status'] == 'unknown') {
                        $tile_arr['unknown_device_status']++;
                    }

                    $t_device_pos = json_decode($obj_traccar->position($value['positionId'])->response);

                    if (isset($value['attributes']['speedLimit']) && (isset($t_device_pos[0]->speed))) {
                        if ($value['attributes']['speedLimit'] < $t_device_pos[0]->speed) {
                            $tile_arr['overspeed']++;
                        }
                    }

                    if (isset($t_device_pos[0]->attributes->battery)) {
                        $batteryLevel = $t_device_pos[0]->attributes->battery;
                    } else if (isset($t_device_pos[0]->attributes->batteryLevel)) {
                        $batteryLevel = $t_device_pos[0]->attributes->batteryLevel;
                    }

                    if ($batteryLevel < 20) {
                        $tile_arr['low_battery']++;
                    }
                }
            } else { }
        }
        if (($session->read('_user_type') == 2)) {    // manager
            // $users = TableRegistry::get('Users');
            // $device = TableRegistry::get('Devices');
            // $tile_arr['users'] = $device->find('all')
            //             ->select(['Devices.id'])
            //          ->join([
            //                 'Deviceusers' => [
            //                     'table' => 'deviceusers',
            //                     'type' => 'LEFT',
            //                     'conditions' => 'Deviceusers.devices_id = Devices.id'
            //                 ]
            //             ])
            //             ->where(['Devices.device_status = 1'])
            //             ->andwhere(['Deviceusers.deviceusers_startdate <= ' => date('Y-m-d H:i:s')])
            //             ->andwhere(['Deviceusers.deviceusers_enddate >= ' => date('Y-m-d H:i:s')])
            //          ->andWhere(['Deviceusers.users_id' => $session->read('_user_id')])
            //          ->count();
            $devicsTable = TableRegistry::get('Devices');

            $_devices = $devicsTable->find('all')
                ->select(['Devices.id'])
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
                ->andwhere(['Deviceusers.deviceusers_status' => 1])
                ->contain(['Devicetypes', 'Devicegroups']);

            $deviceusersTable = TableRegistry::get('Deviceusers');

            $tile_arr['users'] = $deviceusersTable->find('all')
                ->select(['Deviceusers.users_id'])
                ->contain(['Devices', 'Users'])
                ->where(['Deviceusers.devices_id IN ' => $_devices])
                ->andwhere(['Deviceusers.deviceusers_status' => 1])
                ->andwhere(['Deviceusers.users_id != ' => $session->read('_user_id')])
                ->andwhere(['Deviceusers.deviceusers_startdate <= ' => date('Y-m-d H:i:s')])
                ->andwhere(['Deviceusers.deviceusers_enddate >= ' => date('Y-m-d H:i:s')])
                ->group('Deviceusers.users_id')
                ->count();
        } else if (($session->read('_user_type') == 1) ||     //admin
            ($session->read('_user_type') == 5)
        ) {   // super admin

            $users = TableRegistry::get('Users');
            $tile_arr['users'] = $users->find('all')
                ->select(['Users.id'])
                ->where(['Users.usertypes_id = 3'])
                ->andWhere(['Users.created_by ' => $session->read('_user_id')])
                ->count();
            $tile_arr['managers'] = $users->find('all')
                ->select(['Users.id'])
                ->where(['Users.usertypes_id = 2'])
                ->andWhere(['Users.created_by ' => $session->read('_user_id')])
                ->count();
        }
        $tile_arr['managers'] = $session->read('_user_id');
        echo json_encode($tile_arr);
        die();
    }

    private function object_2_array($result)
    {
        $array = array();
        foreach ($result as $key => $value) {
            if (is_object($value)) {
                $array[$key] = self::object_2_array($value);
            }
            if (is_array($value)) {
                $array[$key] = self::object_2_array($value);
            } else {
                $array[$key] = $value;
            }
        }
        return $array;
    }
}
