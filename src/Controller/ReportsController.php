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
    public function index()
    {
        $session = $this->request->session();

        if (($session->read('_user_type') == 1) || ($session->read('_user_type') == 2)) {
            return $this->redirect(['action' => 'managementdashboard']);
        } elseif ($session->read('_user_type') == 3) {
            return $this->redirect(['action' => 'userdashboard']);
        }
    }

    public function overview()
    {
        $session = $this->request->session();
        $deviceTable = TableRegistry::get('Devices');

        $device_targetname = '';
        $device_info = array();
        $start_date = '';
        $end_date = '';

        if ($this->request->is('post')) {
            $device = $deviceTable->find('all')
                ->where(['id' => $this->request->data['devicesid']])
                ->toArray();

            $total_distance = '';

            $obj_traccar = new \App\Utility\Traccar();
            $t_login = $obj_traccar->login();

            $device_targetname = $device[0]->device_targetname;
            // $device_id = $this->request->data['devicesid'];
            $device_id = $device[0]->device_traccarid;

            $start_date = $from_date = AppController::convertutciso($this->request->data['startdate']);
            $end_date = $to_date = AppController::convertutciso($this->request->data['enddate']);

            if ($t_login->responseCode == 200) {
                $t_devices = $obj_traccar->positions($device_id, $from_date, $to_date);
                $device_init_info = json_decode($t_devices->response);

                $init_time = '';
                // echo 'ID|Lat/Lng|Motion|Ignition|serverTime|deviceTime|fixTime|Speed<br>';
                foreach ($device_init_info as $key => $value) {
                    // debug($value);
                    // echo 'ID-' . $value->id . ' Lat/Lng-' . $value->latitude . ', ' . $value->longitude .
                    //     ' Motion-' . (($value->attributes->motion == 1) ? 'Moving' : 'Stopped') .
                    //     ' Ignition-' . ((isset($value->attributes->ignition)) ? (($value->attributes->ignition == 1) ? 'On' : 'Off') : 'NA') .
                    //     ' STime-' . date('Y-m-d H:i:s', strtotime($value->serverTime)) .
                    //     ' DTime-' . date('Y-m-d H:i:s', strtotime($value->deviceTime)) .
                    //     ' Time-' . date('Y-m-d H:i:s', strtotime($value->fixTime)) .
                    //     ' Speed-' . $value->speed . '<br>';

                    // echo $value->id . '|' . $value->latitude . ', ' . $value->longitude .
                    //     '|' . (($value->attributes->motion == 1) ? 'Moving' : 'Stopped') .
                    //     '|' . ((isset($value->attributes->ignition)) ? (($value->attributes->ignition == 1) ? 'On' : 'Off') : 'NA') .
                    //     '|' . date('Y-m-d H:i:s', strtotime($value->serverTime)) .
                    //     '|' . date('Y-m-d H:i:s', strtotime($value->deviceTime)) .
                    //     '|' . date('Y-m-d H:i:s', strtotime($value->fixTime)) .
                    //     '|' . $value->speed . '<br>';

                    if ($init_time == '') {
                        $init_time = date('Y-m-d H:i:s', strtotime($value->fixTime));
                        $_address = self::get_address($value->latitude, $value->longitude, 'RPT_OVERVIEW');
                        
                        $device_info[] = [
                            'date' => date('d-M-Y', strtotime($init_time)),
                            'time' => date('H:i', strtotime($init_time)),
                            'latlong' => $value->latitude . ', ' . $value->longitude,
                            'address' => $_address,
                            'status' => $value->attributes->motion,
                            'speed' => round($value->speed, 2),
                            'distance' => 0
                        ];

                        $totalDistance = $value->attributes->totalDistance;
                    } else {
                        if ((strtotime(date('Y-m-d H:i:s', strtotime($value->fixTime))) -
                            strtotime($init_time)) >= 120) {
                            $init_time = date('Y-m-d H:i:s', strtotime($value->fixTime));
                            $_address = self::get_address($value->latitude, $value->longitude, 'RPT_OVERVIEW');
                            
                            $device_info[] = [
                                'date' => date('d-M-Y', strtotime($init_time)),
                                'time' => date('H:i', strtotime($init_time)),
                                'latlong' => $value->latitude . ', ' . $value->longitude,
                                'address' => $_address,
                                'status' => $value->attributes->motion,
                                'speed' => round($value->speed, 2),
                                'distance' => $value->attributes->totalDistance - $totalDistance
                            ];
                            // $totalDistance = $value->attributes->totalDistance;
                        }
                    }
                }
            }
        }

        if (($session->read('_user_type') == 2) || ($session->read('_user_type') == 3)) {
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
        } else if (($session->read('_user_type') == 1)) {
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
        } else {
            $devices = $deviceTable->find('list', ['valueField' => function ($row) {
                return $row['device_targetname'];
            }]);
        }

        $this->set(compact('devices', 'device_info', 'start_date', 'end_date', 'device_targetname'));
        $this->set('_serialize', ['devices', 'device_info', 'start_date', 'end_date', 'device_targetname']);
    }

    public function overspeed()
    {
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

            $start_date = $from_date = AppController::convertutciso($this->request->data['startdate']);
            $end_date = $to_date = AppController::convertutciso($this->request->data['enddate']);

            if ($t_login->responseCode == 200) {
                $t_devices = $obj_traccar->positions($device_id, $from_date, $to_date);
                $device_init_info = json_decode($t_devices->response);
                // debug($device_init_info);
                $init_time = '';
                $avg_speed = 0;
                $entry_cnt = 0;
                $latlong = '';

                foreach ($device_init_info as $key => $value) {
                    if (($value->speed * 1.85) >= $device[0]->device_overspeed) {
                        if ($init_time == '') {
                            $init_time = date('Y-m-d H:i:s', strtotime($value->fixTime));

                            $device_overspeed[0] = [
                                'date' => date('d-M-Y', strtotime($init_time)),
                                'time' => date('H:i:s', strtotime($init_time)),
                                'latlong' => $value->latitude . ', ' . $value->longitude,
                                'address' => '',
                                'status' => $value->attributes->motion,
                                'speed' => round($value->speed, 2),
                                'distance' => 0,
                                'id' => $value->id,
                                'fixTime' => $value->fixTime,
                                'overspeedlimit' => $device[0]->device_overspeed,
                                'duration' => ''
                            ];
                            $avg_speed += round($value->speed, 2);
                            $entry_cnt++;

                            $totalDistance = $value->attributes->totalDistance;
                        } else {

                            if (($device[0]->device_overspeedduration == 0) || ($device[0]->device_overspeedduration == '')
                            ) {
                                $osduration = 120;
                            } else {
                                $osduration = $device[0]->device_overspeedduration;
                            }

                            if ((strtotime(date('Y-m-d H:i:s', strtotime($value->fixTime))) -
                                strtotime($init_time)) >= $osduration) {
                                $end_time = date('Y-m-d H:i:s', strtotime($value->fixTime));
                                $device_overspeed[1] = [
                                    'date' => date('d-M-Y', strtotime($end_time)),
                                    'time' => date('H:i:s', strtotime($end_time)),
                                    'latlong' => $value->latitude . ', ' . $value->longitude,
                                    'address' => '',
                                    'status' => $value->attributes->motion,
                                    'speed' => round($value->speed, 2),
                                    'distance' => $value->attributes->totalDistance - $totalDistance,
                                    'id' => $value->id,
                                    'fixTime' => $value->fixTime,
                                    'overspeedlimit' => $device[0]->device_overspeed,
                                    'duration' => strtotime($end_time) - strtotime($init_time)
                                ];
                                // $totalDistance = $value->attributes->totalDistance;
                                $avg_speed += round($value->speed, 2);
                                $entry_cnt++;
                            }
                        }
                    } else {

                        if ((!empty($device_overspeed[0])) && (!empty($device_overspeed[1]))) {
                            $avg_speed = $avg_speed / $entry_cnt;
                            $device_overspeed[1]['speed'] = $avg_speed;

                            $latlong = explode(', ', $device_overspeed[0]['latlong']);
                            $device_overspeed[0]['address'] = self::get_address($latlong[0], $latlong[1], 'RPT_OVERSPEED');

                            $latlong = explode(', ', $device_overspeed[1]['latlong']);
                            $device_overspeed[1]['address'] = self::get_address($latlong[0], $latlong[1], 'RPT_OVERSPEED');

                            $device_info[] = $device_overspeed;
                        }

                        $device_overspeed = null;
                        $init_time = '';
                        $entry_cnt = 0;
                        // debug($value->fixTime);
                        // debug($value->speed);
                    }
                }
            }
        }

        if (($session->read('_user_type') == 2) || ($session->read('_user_type') == 3)) {
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
        } else if (($session->read('_user_type') == 1)) {
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
        } else {
            $devices = $deviceTable->find('list', ['limit' => 200]);
        }

        $this->set(compact('devices', 'device_info', 'start_date', 'end_date', 'device_targetname'));
        $this->set('_serialize', ['devices', 'device_info', 'start_date', 'end_date', 'device_targetname']);
    }

    public function gtid()
    {
        $session = $this->request->session();
        $deviceTable = TableRegistry::get('Devices');
        $devices = array();

        if (($session->read('_user_type') == 1)) {
            $devices = $deviceTable->find('all')
                ->select([
                    'Devices.id', 'Devices.device_targetid', 'Devices.device_targetname',
                    'Devices.device_model', 'Users.user_name', 'Users.user_shortname',
                    'Users.user_fname', 'Users.user_lname', 'Users.user_cellphone',
                    'Users.user_address', 'Users.user_city', 'States.state_name'
                ])
                ->join([
                    'Deviceusers' => [
                        'table' => 'deviceusers',
                        'type' => 'LEFT',
                        'conditions' => 'Devices.id = Deviceusers.devices_id'
                    ]
                ])
                ->join([
                    'Users' => [
                        'table' => 'users',
                        'type' => 'LEFT',
                        // 'conditions' => 'Users.id = '.$session->read('_user_id')
                        'conditions' => 'Users.id = Deviceusers.users_id'
                    ]
                ])
                ->join([
                    'States' => [
                        'table' => 'states',
                        'type' => 'LEFT',
                        'conditions' => 'Users.states_id = States.id'
                    ]
                ])
                ->where(['Deviceusers.deviceusers_startdate <= ' => date('Y-m-d H:i:s')])
                ->andwhere(['Deviceusers.deviceusers_enddate >= ' => date('Y-m-d H:i:s')])
                ->andwhere(['Devices.created_by ' => $session->read('_user_id')])
                ->andwhere(['Deviceusers.deviceusers_status ' => 1])
                // ->order(['Devices.id' => 'ASC'])
                ->order(['Users.usertypes_id' => 'ASC'])
                ->toArray();
        }

        $this->set(compact('devices'));
        $this->set('_serialize', ['devices']);
    }

    public function mid()
    {
        $session = $this->request->session();
        $deviceTable = TableRegistry::get('Devices');
        $devices = array();

        if (($session->read('_user_type') == 1)) {
            $devices = $deviceTable->find('all')
                ->select([
                    'Devices.id', 'Devices.device_targetid', 'Devices.device_targetname',
                    'Devices.device_model', 'Users.user_name', 'Users.user_shortname',
                    'Users.user_fname', 'Users.user_lname', 'Users.user_cellphone',
                    'Users.user_address', 'Users.user_city', 'States.state_name'
                ])
                ->join([
                    'Deviceusers' => [
                        'table' => 'deviceusers',
                        'type' => 'LEFT',
                        'conditions' => 'Devices.id = Deviceusers.devices_id'
                    ]
                ])
                ->join([
                    'Users' => [
                        'table' => 'users',
                        'type' => 'LEFT',
                        'conditions' => 'Users.id = Deviceusers.users_id'
                    ]
                ])
                ->join([
                    'States' => [
                        'table' => 'states',
                        'type' => 'LEFT',
                        'conditions' => 'Users.states_id = States.id'
                    ]
                ])
                ->where(['Deviceusers.deviceusers_startdate <= ' => date('Y-m-d H:i:s')])
                ->andwhere(['Deviceusers.deviceusers_enddate >= ' => date('Y-m-d H:i:s')])
                ->andwhere(['Devices.created_by ' => $session->read('_user_id')])
                ->andwhere(['Deviceusers.deviceusers_status ' => 1])
                ->order(['Devices.id' => 'ASC'])
                ->toArray();
        }

        $this->set(compact('devices'));
        $this->set('_serialize', ['devices']);
    }

    public function ismd()
    {
        $session = $this->request->session();
        $deviceTable = TableRegistry::get('Devices');

        $device_targetname = '';
        $device_info = array();
        $tmp_device_info = array();
        $start_date = '';
        $end_date = '';
        $avg_speed = $entry_cnt = 0;

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

            $start_date = $from_date = AppController::convertutciso($this->request->data['startdate']);
            $end_date = $to_date = AppController::convertutciso($this->request->data['enddate']);

            if ($t_login->responseCode == 200) {
                $t_devices = $obj_traccar->positions($device_id, $from_date, $to_date);
                $device_init_info = json_decode($t_devices->response);
                $init_time = '';
                $init_status = '';

                foreach ($device_init_info as $key => $value) {
                    // debug($value->id.','.$value->latitude.','.$value->longitude.','.
                    //         date('d-M-Y H:i:s', strtotime($value->fixTime)).','.(($value->attributes->motion)?'moving':'stopped'));

                    if ($init_status == '') {

                        if (strtotime($from_date) < strtotime($value->fixTime)) {
                            $tmp_device_info[0] = [
                                'date' => date('d-M-Y H:i:s', strtotime($from_date)),
                                'time' => '',
                                'latlong' => $value->latitude . ', ' . $value->longitude,
                                'address' => self::get_address($value->latitude, $value->longitude, 'RPT_ISMD'),
                                'status' => 'stopped',
                                'speed' => 0,
                                'distance' => 0,
                                'pos_id' => 0
                            ];

                            $tmp_device_info[1] = [
                                'date' => date('d-M-Y H:i:s', strtotime($value->fixTime)),
                                'time' => '',
                                'latlong' => $value->latitude . ', ' . $value->longitude,
                                'address' => self::get_address($value->latitude, $value->longitude, 'RPT_ISMD'),
                                'status' => 'stopped',
                                'speed' => 0,
                                'distance' => $value->attributes->distance,
                                'pos_id' => 0
                            ];

                            array_push($device_info, $tmp_device_info);
                            unset($tmp_device_info);
                        }

                        $init_status = ($value->attributes->motion) ? 'moving' : 'stopped';
                        $tmp_device_info[0] = [
                            'date' => date('d-M-Y H:i:s', strtotime($value->fixTime)),
                            'time' => '',
                            'latlong' => $value->latitude . ', ' . $value->longitude,
                            'address' => self::get_address($value->latitude, $value->longitude, 'RPT_ISMD'),
                            'status' => ($value->attributes->motion) ? 'moving' : 'stopped',
                            'speed' => round($value->speed, 2),
                            'distance' => 0,
                            'pos_id' => $value->id
                        ];

                        $avg_speed = round($value->speed, 2);
                        $entry_cnt++;

                        $totalDistance = $value->attributes->totalDistance;
                    } else {
                        if ($init_status == (($value->attributes->motion) ? 'moving' : 'stopped')) {
                            $tmp_device_info[1] = [
                                'date' => date('d-M-Y H:i:s', strtotime($value->fixTime)),
                                'time' => '',
                                'latlong' => $value->latitude . ', ' . $value->longitude,
                                'address' => '',
                                'status' => ($value->attributes->motion) ? 'moving' : 'stopped',
                                'speed' => round($value->speed, 2),
                                'distance' => $value->attributes->totalDistance - $totalDistance,
                                'pos_id' => $value->id
                            ];
                            // $totalDistance = $value->attributes->totalDistance;
                            $avg_speed += round($value->speed, 2);
                            $entry_cnt++;
                        } else {
                            if (!empty($tmp_device_info[1])) {
                                if ($tmp_device_info[0]['status'] == 'stopped') {
                                    // if (((strtotime($tmp_device_info[1]['date']) -
                                    if (((strtotime($value->fixTime) -
                                        strtotime($tmp_device_info[0]['date']))) >= 120) {
                                        $tmp_device_info[1]['speed'] = round(($avg_speed / $entry_cnt), 2);
                                        $tmp_ltlng = explode(', ', $tmp_device_info[1]['latlong']);
                                        $tmp_device_info[1]['address'] = self::get_address($tmp_ltlng[0], $tmp_ltlng[1], 'RPT_ISMD');
                                        $tmp_device_info[1]['date'] = date('d-M-Y H:i:s', strtotime($value->fixTime));
                                        $tmp_device_info[1]['distance'] = $value->attributes->totalDistance - $totalDistance;
                                        array_push($device_info, $tmp_device_info);
                                    } else {
                                        $tmp_device_info[0]['status'] = $init_status = ($value->attributes->motion) ? 'moving' : 'stopped';

                                        $tmp_device_info[1] = [
                                            'date' => date('d-M-Y H:i:s', strtotime($value->fixTime)),
                                            'time' => '',
                                            'latlong' => $value->latitude . ', ' . $value->longitude,
                                            'address' => self::get_address($value->latitude, $value->longitude, 'RPT_ISMD'),
                                            'status' => ($value->attributes->motion) ? 'moving' : 'stopped',
                                            'speed' => round($value->speed, 2),
                                            'distance' => 0,
                                            'pos_id' => $value->id
                                        ];

                                        $totalDistance = $value->attributes->totalDistance;
                                        $avg_speed = round($value->speed, 2);
                                        $entry_cnt = 1;
                                        continue;
                                        // unset($tmp_device_info);
                                    }
                                } else {
                                    $tmp_ltlng = explode(', ', $tmp_device_info[0]['latlong']);
                                    $tmp_device_info[0]['address'] = self::get_address($tmp_ltlng[0], $tmp_ltlng[1], 'RPT_ISMD');
                                    $tmp_ltlng = explode(', ', $tmp_device_info[1]['latlong']);
                                    $tmp_device_info[1]['address'] = self::get_address($tmp_ltlng[0], $tmp_ltlng[1], 'RPT_ISMD');

                                    // $tmp_device_info[1]['speed'] = round(($avg_speed / $entry_cnt), 2);
                                    //
                                    if (count($device_info) != 0) {
                                        if ($device_info[(count($device_info) - 1)][0]['status'] == 'stopped') {
                                            array_push($device_info, $tmp_device_info);
                                        } else {
                                            $device_info[(count($device_info) - 1)][1] = [
                                                'date' => $tmp_device_info[1]['date'],
                                                'time' => '',
                                                'latlong' => $tmp_device_info[1]['latlong'],
                                                'address' => $tmp_device_info[1]['address'],
                                                'status' => $tmp_device_info[1]['status'],
                                                'speed' => round((($device_info[(count($device_info) - 1)][1]['speed'] + $tmp_device_info[1]['speed']) / 2), 2),
                                                'distance' => $tmp_device_info[1]['distance'] + $device_info[(count($device_info) - 1)][1]['distance'],
                                                'pos_id' => $tmp_device_info[1]['pos_id']
                                            ];
                                        }
                                    } else {
                                        array_push($device_info, $tmp_device_info);
                                    }
                                }
                            }
                            unset($tmp_device_info);
                            $tmp_device_info = array();
                            $init_status = ($value->attributes->motion) ? 'moving' : 'stopped';

                            $tmp_device_info[0] = [
                                'date' => date('d-M-Y H:i:s', strtotime($value->fixTime)),
                                'time' => '',
                                'latlong' => $value->latitude . ', ' . $value->longitude,
                                'address' => self::get_address($value->latitude, $value->longitude, 'RPT_ISMD'),
                                'status' => ($value->attributes->motion) ? 'moving' : 'stopped',
                                'speed' => round($value->speed, 2),
                                'distance' => 0,
                                'pos_id' => $value->id
                            ];

                            $totalDistance = $value->attributes->totalDistance;
                            $avg_speed = round($value->speed, 2);
                            $entry_cnt = 1;
                        }
                    }
                }

                // it may happen the device is on same state beyond the end time. In that case it will not encounter a state cahnge
                // and will not push to the array. so need to push the last record chuck manully.
                if (!empty($tmp_device_info)) {
                    array_push($device_info, $tmp_device_info);
                }
            }

            // check if the last added record end time is matching with the selected time. 
            // if not replace the end time withe the selected time and put the time difference
            // as duration
            // $_endtime  = 
            $device_info[(count($device_info) - 1)][1]['date'] = date('d-M-Y H:i:s', strtotime($this->request->data['enddate']));
        }

        if (($session->read('_user_type') == 2) || ($session->read('_user_type') == 3)) {
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
        } else if (($session->read('_user_type') == 1)) {
            // $devices = $deviceTable->find('list', ['valueField' => function ($row) {
            //     return $row['device_targetname'];
            // }]);
            $devices = $deviceTable->find('list', ['valueField' => function ($row) {
                return $row['device_targetname'];
            }])
                    ->where(['Devices.device_status = 1'])
                    ->andwhere(['Devices.created_by ' => $session->read('_user_id')])
                    ->toArray();
        }
        
        $this->set(compact('devices', 'device_info', 'start_date', 'end_date', 'device_targetname'));
        $this->set('_serialize', ['devices', 'device_info', 'start_date', 'end_date', 'device_targetname']);
    }

    public function gpstop()
    {
        $session = $this->request->session();
        $deviceTable = TableRegistry::get('Devices');

        $device_targetname = '';
        $device_info = array();
        $device_stops = array();
        $start_date = '';
        $end_date = '';
        $device_info_cnt = 0;

        if ($this->request->is('post')) {
            if (($session->read('_user_type') != 5)) {
                $devices = $deviceTable->find('all')
                    ->select([
                        'Devices.id', 'Devices.device_traccarid', 'Devices.device_targetid',
                        'Devices.device_targetname', 'Devices.device_identifier',
                        'Devices.device_stoppagetime'
                    ])
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
            } else {
                $devices = $deviceTable->find('all', ['limit' => 200]);
            }

            $obj_traccar = new \App\Utility\Traccar();
            $t_login = $obj_traccar->login();

            $start_date = $from_date = AppController::convertutciso($this->request->data['startdate']);
            $end_date = $to_date = AppController::convertutciso($this->request->data['enddate']);

            if ($t_login->responseCode == 200) {

                foreach ($devices as $key => $value) {
                    $device_targetname = $value->device_targetname;
                    $device_targetid = $value->device_targetid;
                    $device_stoppagetime = (($value->device_stoppagetime == '') ? 120 : $value->device_stoppagetime);
                    $device_id = $value->device_traccarid;

                    $t_devices = $obj_traccar->positions($device_id, $from_date, $to_date);
                    $device_init_info = json_decode($t_devices->response);

                    foreach ($device_init_info as $_key => $posvalue) {

                        if (!$posvalue->attributes->motion) {
                            if (empty($device_stops)) {
                                $device_stops[0] = [
                                    'date' => date('d-M-Y H:i:s', strtotime($posvalue->fixTime)),
                                    'gtname' => $device_targetname,
                                    'gtid' => $device_targetid,
                                    'latlong' => $posvalue->latitude . ', ' . $posvalue->longitude,
                                    'address' => '',
                                    'status' => $posvalue->attributes->motion,
                                    'stoppagetime' => $device_stoppagetime,
                                    'speed' => round($posvalue->speed, 2),
                                    'distance' => 0,
                                    'duration' => 0,
                                    'id' => $posvalue->id,
                                    'fixTime' => $posvalue->fixTime
                                ];
                            } else {
                                $device_stops[1] = [
                                    'date' => date('d-M-Y H:i:s', strtotime($posvalue->fixTime)),
                                    'gtname' => $device_targetname,
                                    'gtid' => $device_targetid,
                                    'latlong' => $posvalue->latitude . ', ' . $posvalue->longitude,
                                    'address' => '',
                                    'status' => $posvalue->attributes->motion,
                                    'stoppagetime' => $device_stoppagetime,
                                    'speed' => round($posvalue->speed, 2),
                                    'distance' => 0,
                                    'duration' => 0,
                                    'id' => $posvalue->id,
                                    'fixTime' => $posvalue->fixTime
                                ];
                            }
                        } else {
                            if (!empty($device_stops[1])) {
                                $_diff = strtotime($device_stops[1]['date']) - strtotime($device_stops[0]['date']);

                                // if (date('i', $_diff) >= $device_stoppagetime){
                                if ($_diff >= 120) {
                                    $tmp_ltlng = explode(', ', $device_stops[1]['latlong']);
                                    $device_stops[1]['address'] = self::get_address($tmp_ltlng[0], $tmp_ltlng[1], 'RPT_GPSTOP');
                                    
                                    $device_stops[1]['duration'] = $_diff;
                                    array_push($device_info, $device_stops);
                                }
                                // $device_info[$device_info_cnt] = $device_stops;
                                // $device_info_cnt++;
                            }
                            unset($device_stops);
                        }
                    }
                    unset($device_stops);
                    $device_init_info = '';
                }
            }
        }

        $this->set(compact('devices', 'device_info', 'start_date', 'end_date', 'device_targetname'));
        $this->set('_serialize', ['devices', 'device_info', 'start_date', 'end_date', 'device_targetname']);
    }

    public function pboverview()
    {
        $session = $this->request->session();
        $deviceTable = TableRegistry::get('Devices');
        $device_init_info = $start_date = $end_date = '';
        $device_id = 0;
        $device_init_info = '';
        $device_targetname = '';

        $obj_traccar = new \App\Utility\Traccar();
        $t_login = $obj_traccar->login();

        if ($this->request->is('post')) {

            if (isset($this->request->data['startdate'])) {
                $start_date = $this->request->data['startdate'];
                $end_date = $this->request->data['enddate'];
            } else {
                $start_date = date('Y-m-d') . ' 00:00';
                $end_date = date('Y-m-d') . ' 23:59';
            }

            if (($session->read('_user_type') == 2) || ($session->read('_user_type') == 3)) {
                $devices = $deviceTable->find('all', [])
                    ->join([
                        'Deviceusers' => [
                            'table' => 'deviceusers',
                            'type' => 'LEFT',
                            'conditions' => 'Deviceusers.devices_id = Devices.id'
                        ]
                    ])
                    ->where(['Devices.device_status = 1'])
                    ->andwhere(['Deviceusers.deviceusers_startdate <= "' . $start_date . '"'])
                    ->andwhere(['Deviceusers.deviceusers_enddate >= "' . $end_date . '"'])
                    ->andwhere(['Deviceusers.users_id ' => $session->read('_user_id')])
                    ->andwhere(['Devices.device_targetname' => $session->read('_device_targetname')])
                    ->toArray();
            } else {
                $devices = $deviceTable->find('all', [])
                    ->select([
                        'Devices.id', 'Devices.device_traccarid',
                        'Devices.device_targetname', 'Devices.device_identifier'
                    ])
                    ->where(['Devices.device_status = 1'])
                    ->andwhere(['Devices.created_by ' => $session->read('_user_id')])
                    ->toArray();
                // ->where(['Devices.device_status = 1'])
                // ->andwhere(['Devices.device_targetname' => $session->read('_device_targetname')])
                // ->toArray();
            }

            if (!empty($devices)) {
                $device_id = $devices[0]['device_traccarid'];
                $device_targetname = $devices[0]['device_targetname'];

                $start_date = $from_date = AppController::convertutciso($start_date);
                $end_date = $to_date = AppController::convertutciso($end_date);

                if ($t_login->responseCode == 200) {
                    $t_devices = $obj_traccar->positions($device_id, $from_date, $to_date);
                    $device_init_info = json_decode($t_devices->response);

                    if (empty($device_init_info)) {
                        $this->Flash->error(__('No location data found.', 'Device'));
                        $device_init_info = 0;
                    } else {
                        $device_init_info = json_encode($device_init_info);
                    }
                } else { }
            } else {
                $device_init_info = 0;
                $this->Flash->error(__('You don\'t have access to the device, between the selected date/time range.', 'Device'));
            }
        }

        if (($session->read('_user_type') == 2) || ($session->read('_user_type') == 3)) {
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
        } else if (($session->read('_user_type') == 1)) {
            $devices = $deviceTable->find('list', ['valueField' => function ($row) {
                return $row['device_targetname'];
            }])
                ->select([
                    'Devices.id', 'Devices.device_traccarid',
                    'Devices.device_targetname', 'Devices.device_identifier'
                ])
                ->where(['Devices.device_status = 1'])
                ->andwhere(['Devices.created_by ' => $session->read('_user_id')])
                ->toArray();
        } else {
            $devices = $deviceTable->find('list', []);
        }

        if ($start_date == '') {
            $start_date = $end_date = date('Y-m-d H:i:s');
        }
        $this->set(compact('device_id', 'device_targetname', 'devices', 'device_init_info', 'start_date', 'end_date'));
    }

    public function gmileage()
    {
        $session = $this->request->session();
        $deviceTable = TableRegistry::get('Devices');

        $device_targetname = '';
        $device_info = array();
        $device_stops = array(); //[0 => ['date' => ''], 1 => ['date' => '']];
        $device_overspeed = array();
        $tmp_device_info = array(
            'device_targetname' => '', 'device_targetid' => '', 'device_stoppagetime' => '', 'device_mileage' => '', 'device_overspeed' => '',  'device_overspeedduration' => '',
            'device_id' => 0, 'start_distance' => 0, 'end_distance' => 0, 'overspeed' => 0, 'stops' => 0, 'acc' => 0
        );
        $start_date = $end_date = $init_time = $init_status = '';
        $device_info_cnt = 0;

        if ($this->request->is('post')) {

            if (($session->read('_user_type') == 2) || ($session->read('_user_type') == 3)) {
                $devices = $deviceTable->find('all', [])
                    ->join([
                        'Deviceusers' => [
                            'table' => 'deviceusers',
                            'type' => 'LEFT',
                            'conditions' => 'Deviceusers.devices_id = Devices.id'
                        ]
                    ])
                    ->where(['Devices.device_status = 1'])
                    ->andwhere(['Deviceusers.deviceusers_startdate <= "' . $this->request->data['startdate'] . '"'])
                    ->andwhere(['Deviceusers.deviceusers_enddate >= "' . $this->request->data['enddate'] . '"'])
                    ->andwhere(['Deviceusers.users_id ' => $session->read('_user_id')])
                    // ->andwhere(['Devices.device_targetname' => $session->read('_device_targetname')]);
                    ->toArray();
            } else {
                $devices = $deviceTable->find('all', [])
                    ->where(['Devices.device_status = 1'])
                    ->andwhere(['Devices.created_by ' => $session->read('_user_id')])
                    ->toArray();
            }

            $obj_traccar = new \App\Utility\Traccar();
            $t_login = $obj_traccar->login();

            $start_date = $from_date = AppController::convertutciso($this->request->data['startdate']);
            $end_date = $to_date = AppController::convertutciso($this->request->data['enddate']);

            if ($t_login->responseCode == 200) {

                foreach ($devices as $key => $value) {

                    $tmp_device_info = array(
                        'device_targetname' => '', 'device_targetid' => '', 'device_stoppagetime' => '', 'device_mileage' => '', 'device_overspeed' => '',  'device_overspeedduration' => '',
                        'device_id' => 0, 'start_distance' => 0, 'end_distance' => 0, 'overspeed' => 0, 'stops' => 0, 'acc' => 0
                    );

                    $tmp_device_info['device_targetname'] = $value->device_targetname;
                    $tmp_device_info['device_targetid'] = $value->device_targetid;
                    $tmp_device_info['device_stoppagetime'] = $value->device_stoppagetime;
                    $tmp_device_info['device_mileage'] = $value->device_milage;
                    $tmp_device_info['device_overspeed'] = $value->device_overspeed;
                    $tmp_device_info['device_overspeedduration'] = $value->device_overspeedduration;
                    $tmp_device_info['device_id'] = $value->device_traccarid;
                    $tmp_device_info['start_distance'] = 0;
                    $tmp_device_info['end_distance'] = 0;
                    $tmp_device_info['distance'] = 0;
                    $tmp_device_info['overspeed'] = 0;
                    $tmp_device_info['stops'] = 0;
                    $tmp_device_info['acc'] = 0;
                    $acc_stat = $tmp_acc_stat = '';

                    // if ($value->id == 10) {   // this is TEMPORARY

                    $t_devices = $obj_traccar->positions($value->device_traccarid, $from_date, $to_date);
                    $device_init_info = json_decode($t_devices->response);

                    foreach ($device_init_info as $_key => $posvalue) {

                        if ($tmp_device_info['start_distance'] == 0) {
                            $tmp_device_info['start_distance'] = $posvalue->attributes->totalDistance;
                        }
                        $tmp_device_info['end_distance'] = $posvalue->attributes->totalDistance;

                        $tmp_device_info['distance'] = $tmp_device_info['distance'] + $posvalue->attributes->distance;

                        $tmp_acc_stat = (isset($posvalue->attributes->ignition) ? 'F' : 'T');

                        if ($acc_stat == '') {
                            $acc_stat = $tmp_acc_stat;
                        } else if ($acc_stat != $tmp_acc_stat) {
                            $tmp_device_info['acc']++;
                            $acc_stat = $tmp_acc_stat;
                        }

                        if ($posvalue->speed >= $tmp_device_info['device_overspeed']) {
                            if (empty($device_overspeed[0])) {
                                $device_overspeed[0] = ['date' => date('Y-m-d H:i:s', strtotime($posvalue->fixTime))];
                            } else {
                                $device_overspeed[1] = ['date' => date('Y-m-d H:i:s', strtotime($posvalue->fixTime))];
                            }
                        } else {
                            if ((!empty($device_overspeed[0])) && (!empty($device_overspeed[1]))) {
                                if ((strtotime(date('Y-m-d H:i:s', strtotime($device_overspeed[1]['date']))) -
                                    strtotime($device_overspeed[0]['date'])) >= $tmp_device_info['device_overspeedduration']) {
                                    $tmp_device_info['overspeed']++;
                                }
                            }
                            unset($device_overspeed);
                        }

                        if ($init_status == '') {
                            if ((($posvalue->attributes->motion) ? 'moving' : 'stopped') == 'stopped') {
                                $init_status = ($posvalue->attributes->motion) ? 'moving' : 'stopped';
                                $device_stops[0] = ['date' => date('Y-m-d H:i:s', strtotime($posvalue->fixTime))];
                                $device_stops[1] = ['date' => date('Y-m-d H:i:s', strtotime($posvalue->fixTime))];
                            }
                        } else {
                            if ($init_status == (($posvalue->attributes->motion) ? 'moving' : 'stopped')) {
                                if (empty($device_stops[0])) {
                                    $device_stops[0] = ['date' => date('Y-m-d H:i:s', strtotime($posvalue->fixTime))];
                                    $device_stops[1] = ['date' => date('Y-m-d H:i:s', strtotime($posvalue->fixTime))];
                                } else {
                                    $device_stops[1] = ['date' => date('Y-m-d H:i:s', strtotime($posvalue->fixTime))];
                                }
                            } else {
                                if ((strtotime($posvalue->fixTime) -
                                    strtotime($device_stops[0]['date'])) >= 120) {
                                    $tmp_device_info['stops']++;
                                }
                                unset($device_stops);
                                $init_status = '';
                            }
                        }
                    }
                    // }   // this is TEMPORARY

                    array_push($device_info, $tmp_device_info);

                    unset($tmp_device_info);
                    $tmp_device_info = '';
                }
            }
        }else{
            if (($session->read('_user_type') == 2) || ($session->read('_user_type') == 3)) {
                $devices = $deviceTable->find('all', [])
                    ->join([
                        'Deviceusers' => [
                            'table' => 'deviceusers',
                            'type' => 'LEFT',
                            'conditions' => 'Deviceusers.devices_id = Devices.id'
                        ]
                    ])
                    ->where(['Devices.device_status = 1'])
                    ->andwhere(['Deviceusers.deviceusers_startdate <= "' . $this->request->data['startdate'] . '"'])
                    ->andwhere(['Deviceusers.deviceusers_enddate >= "' . $this->request->data['enddate'] . '"'])
                    ->andwhere(['Deviceusers.users_id ' => $session->read('_user_id')])
                    // ->andwhere(['Devices.device_targetname' => $session->read('_device_targetname')]);
                    ->toArray();
            } else {
                $devices = $deviceTable->find('all', [])
                    ->where(['Devices.device_status = 1'])
                    ->andwhere(['Devices.created_by ' => $session->read('_user_id')])
                    ->toArray();
            }

            $obj_traccar = new \App\Utility\Traccar();
            $t_login = $obj_traccar->login();
            $request_data_startdate = date('Y-m-01 00:00:00');
            $request_data_enddate = date('Y-m-d H:i:s');
            $start_date = $from_date = AppController::convertutciso($request_data_startdate);
            $end_date = $to_date = AppController::convertutciso($request_data_enddate);

            if ($t_login->responseCode == 200) {

                foreach ($devices as $key => $value) {

                    $tmp_device_info = array(
                        'device_targetname' => '', 'device_targetid' => '', 'device_stoppagetime' => '', 'device_mileage' => '', 'device_overspeed' => '',  'device_overspeedduration' => '',
                        'device_id' => 0, 'start_distance' => 0, 'end_distance' => 0, 'overspeed' => 0, 'stops' => 0, 'acc' => 0
                    );

                    $tmp_device_info['device_targetname'] = $value->device_targetname;
                    $tmp_device_info['device_targetid'] = $value->device_targetid;
                    $tmp_device_info['device_stoppagetime'] = $value->device_stoppagetime;
                    $tmp_device_info['device_mileage'] = $value->device_milage;
                    $tmp_device_info['device_overspeed'] = $value->device_overspeed;
                    $tmp_device_info['device_overspeedduration'] = $value->device_overspeedduration;
                    $tmp_device_info['device_id'] = $value->device_traccarid;
                    $tmp_device_info['start_distance'] = 0;
                    $tmp_device_info['end_distance'] = 0;
                    $tmp_device_info['distance'] = 0;
                    $tmp_device_info['overspeed'] = 0;
                    $tmp_device_info['stops'] = 0;
                    $tmp_device_info['acc'] = 0;
                    $acc_stat = $tmp_acc_stat = '';

                    // if ($value->id == 10) {   // this is TEMPORARY

                    $t_devices = $obj_traccar->positions($value->device_traccarid, $from_date, $to_date);
                    $device_init_info = json_decode($t_devices->response);

                    foreach ($device_init_info as $_key => $posvalue) {

                        if ($tmp_device_info['start_distance'] == 0) {
                            $tmp_device_info['start_distance'] = $posvalue->attributes->totalDistance;
                        }
                        $tmp_device_info['end_distance'] = $posvalue->attributes->totalDistance;

                        $tmp_device_info['distance'] = $tmp_device_info['distance'] + $posvalue->attributes->distance;

                        $tmp_acc_stat = (isset($posvalue->attributes->ignition) ? 'F' : 'T');

                        if ($acc_stat == '') {
                            $acc_stat = $tmp_acc_stat;
                        } else if ($acc_stat != $tmp_acc_stat) {
                            $tmp_device_info['acc']++;
                            $acc_stat = $tmp_acc_stat;
                        }

                        if ($posvalue->speed >= $tmp_device_info['device_overspeed']) {
                            if (empty($device_overspeed[0])) {
                                $device_overspeed[0] = ['date' => date('Y-m-d H:i:s', strtotime($posvalue->fixTime))];
                            } else {
                                $device_overspeed[1] = ['date' => date('Y-m-d H:i:s', strtotime($posvalue->fixTime))];
                            }
                        } else {
                            if ((!empty($device_overspeed[0])) && (!empty($device_overspeed[1]))) {
                                if ((strtotime(date('Y-m-d H:i:s', strtotime($device_overspeed[1]['date']))) -
                                    strtotime($device_overspeed[0]['date'])) >= $tmp_device_info['device_overspeedduration']) {
                                    $tmp_device_info['overspeed']++;
                                }
                            }
                            unset($device_overspeed);
                        }

                        if ($init_status == '') {
                            if ((($posvalue->attributes->motion) ? 'moving' : 'stopped') == 'stopped') {
                                $init_status = ($posvalue->attributes->motion) ? 'moving' : 'stopped';
                                $device_stops[0] = ['date' => date('Y-m-d H:i:s', strtotime($posvalue->fixTime))];
                                $device_stops[1] = ['date' => date('Y-m-d H:i:s', strtotime($posvalue->fixTime))];
                            }
                        } else {
                            if ($init_status == (($posvalue->attributes->motion) ? 'moving' : 'stopped')) {
                                if (empty($device_stops[0])) {
                                    $device_stops[0] = ['date' => date('Y-m-d H:i:s', strtotime($posvalue->fixTime))];
                                    $device_stops[1] = ['date' => date('Y-m-d H:i:s', strtotime($posvalue->fixTime))];
                                } else {
                                    $device_stops[1] = ['date' => date('Y-m-d H:i:s', strtotime($posvalue->fixTime))];
                                }
                            } else {
                                if ((strtotime($posvalue->fixTime) -
                                    strtotime($device_stops[0]['date'])) >= 120) {
                                    $tmp_device_info['stops']++;
                                }
                                unset($device_stops);
                                $init_status = '';
                            }
                        }
                    }
                    // }   // this is TEMPORARY

                    array_push($device_info, $tmp_device_info);

                    unset($tmp_device_info);
                    $tmp_device_info = '';
                }
            }
        }
        // echo "<pre>";print_r($device_info);exit;
        $this->set(compact('devices', 'device_info', 'start_date', 'end_date', 'device_targetname'));
        $this->set('_serialize', ['devices', 'device_info', 'start_date', 'end_date', 'device_targetname']);
    }

    public function glsexportexcel(){
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="'.date('dmyhis').'.csv"');
        $data = array();
        array_push($data, " , , , , Group Live Summary Report, , , , ");
        array_push($data, date('d-M-Y H:i'));
        array_push($data, "SL. No.,Target Name,Status,Motion,Speed (Km/Hr),Stop & Movement duration,ACC Satus,Battery Level,Location");
        if($_POST['device_info']){
            $device_info = json_decode($_POST['device_info']);
            $rowcnt = 1;   
            foreach($device_info as $key => $value){
                if(is_numeric($value->device_speed)){
                    $speed =  round(($value->device_speed * 1.85), 2);
                }else{
                    $speed = 0;
                }                
                array_push($data, "".$rowcnt.",".$value->device_targetname.",".$value->device_status.",".$value->device_motion.", ".$speed.",".$value->stop_movement_duration.",".$value->device_acc.",".$value->device_battery.",".$value->device_latlng."");
                $rowcnt++;
            }
        }
        foreach ($data as $d) {
            $line = explode(',', $d);
            foreach ($line as $l) { echo $l.","; }
            echo PHP_EOL;
        }
        exit; 
    }
    public function gmileageexportexcel(){
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="'.date('dmyhis').'.csv"');
        $data = array();
        array_push($data, " , , , , Group Mileage Report, , , , ");
        array_push($data, "SL. No.,Target Name,Mileage (Km),Movement Duration,Stops,Stop Duration,Over Speed Occurances,Over Speed Duration,Fuel Consumption (L)");
        if($_POST['device_info']){
            $device_info = json_decode($_POST['device_info']);
            $rowcnt = 1;   
            foreach($device_info as $key => $value){
                $movement_duration = round((($value->distance) / 1000), 2);
                $fuel_consumption = round(((($value->device_mileage / 100) * ($value->end_distance - $value->start_distance)) / 1000), 2);
                array_push($data, "".$rowcnt.",".$value->device_targetname.",".$value->device_mileage.",".$movement_duration.",".$value->stops.",".$value->device_stoppagetime.",".$value->device_overspeed.",".$value->device_overspeedduration.",".$fuel_consumption."");
                $rowcnt++;
            }
        }
        foreach ($data as $d) {
            $line = explode(',', $d);
            foreach ($line as $l) { echo $l.","; }
            echo PHP_EOL;
        }
        exit; 
    }
    public function airconditionexportexcel(){
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="'.date('dmyhis').'.csv"');
        $data = array();
        array_push($data, " , , , , Air Conditioning Summary Report, , , , ");
        array_push($data, date('d-M-Y H:i'));
        array_push($data, "SL. No.,Target Name,ACC Duration,ACC Distance (KM),Non ACC duration,Non ACC Distance (KM)");
        if($_POST['device_info']){
            $device_info = json_decode($_POST['device_info']);
            $rowcnt = 1;   
            foreach($device_info as $key => $value){
                if(is_numeric($value->device_speed)){
                    $speed =  round(($value->device_speed * 1.85), 2);
                }else{
                    $speed = 0;
                }                
                array_push($data, "".$rowcnt.",".$value->device_targetname.",".$speed.",".$value->stop_movement_duration.", , ");
                $rowcnt++;
            }
        }
        foreach ($data as $d) {
            $line = explode(',', $d);
            foreach ($line as $l) { echo $l.","; }
            echo PHP_EOL;
        }
        exit; 
    }
    public function airconditioningsummary(){
        $session = $this->request->session();
        $deviceTable = TableRegistry::get('Devices');

        $device_targetname = '';
        $device_info = array();
        $device_stops = array(); //[0 => ['date' => ''], 1 => ['date' => '']];
        $device_overspeed = array();
        $tmp_device_info = array(
            'device_targetname' => '', 'device_targetid' => '', 'device_stoppagetime' => '', 'device_mileage' => '', 'device_overspeed' => '',  'device_overspeedduration' => '',
            'device_id' => 0, 'start_distance' => 0, 'end_distance' => 0, 'overspeed' => 0, 'stops' => 0, 'acc' => 0
        );
        $start_date = $end_date = $init_time = $init_status = '';
        $device_info_cnt = 0;

        $to_date = AppController::convertutciso(date('d-m-Y H:i:s'));
        $from_date = AppController::convertutciso(date('d-m-Y H:i:s', (strtotime(date('d-m-Y H:i:s')) - 1 * 60)));

        if (($session->read('_user_type') == 2) || ($session->read('_user_type') == 3)) {
            $devices = $deviceTable->find('all', [])
                ->join([
                    'Deviceusers' => [
                        'table' => 'deviceusers',
                        'type' => 'LEFT',
                        'conditions' => 'Deviceusers.devices_id = Devices.id'
                    ]
                ])
                ->where(['Devices.device_status = 1'])
                ->andwhere(['Deviceusers.deviceusers_startdate <= "' . $from_date . '"'])
                ->andwhere(['Deviceusers.deviceusers_enddate >= "' . $to_date . '"'])
                ->andwhere(['Deviceusers.users_id ' => $session->read('_user_id')])
                // ->andwhere(['Devices.device_targetname' => $session->read('_device_targetname')]);
                ->toArray();
        } else {
            if($_GET['target_name']){
                if(!in_array('all', $_GET['target_name'])){
                    $devices = $deviceTable->find('all', [])
                        ->where(['Devices.device_status = 1'])
                        ->andwhere(['Devices.created_by ' => $session->read('_user_id')])
                        ->andwhere(['id IN' => $_GET['target_name']])
                        ->toArray();
                }else{
                    $devices = $deviceTable->find('all', [])
                        ->where(['Devices.device_status = 1'])
                        ->andwhere(['Devices.created_by ' => $session->read('_user_id')])
                        ->toArray();
                }                
            }else{
                $devices = $deviceTable->find('all', [])
                    ->where(['Devices.device_status = 1'])
                    ->andwhere(['Devices.created_by ' => $session->read('_user_id')])
                    ->toArray();
            }
            
        }

        $obj_traccar = new \App\Utility\Traccar();
        $t_login = $obj_traccar->login();

        if ($t_login->responseCode == 200) {
            foreach ($devices as $key => $value) {
                // if ($value->id == 1) {   // this is TEMPORARY
                $tmp_device_info = array(
                    'device_targetname' => '', 'device_targetid' => '', 'device_stoppagetime' => '', 'device_mileage' => '', 'device_overspeed' => '',  'device_overspeedduration' => '',
                    'device_id' => 0, 'start_distance' => 0, 'end_distance' => 0, 'overspeed' => 0, 'stops' => 0, 'acc' => 0
                );

                $tmp_device_info['device_targetname'] = $value->device_targetname;
                $tmp_device_info['device_targetid'] = $value->device_targetid;

                $tmp_device_info['device_stopmovement'] = '';
                $tmp_device_info['address'] = '';

                // get device info
                $t_devices = $obj_traccar->devices('id=' . $value->device_traccarid);
                $t_devices = json_decode($t_devices->response);
                
                $tmp_device_info['device_status'] = $t_devices[0]->status;

                $_startdate = date('Y-m-d H:i:s', strtotime('-4 days', strtotime(date('Y-m-d H:i:s'))));
                $_startdate = date('Y-m-d H:i:s', strtotime('-330 minutes', strtotime($_startdate)));
                $_enddate = date('Y-m-d H:i:s', strtotime('-330 minutes', strtotime(date('Y-m-d H:i:s'))));

                $_startdate = date('c', strtotime(date($_startdate)));
                $_enddate = date('c', strtotime(date($_enddate)));

                $_startdate = substr($_startdate, 0, strpos($_startdate, '+')) . 'Z';
                $_enddate = substr($_enddate, 0, strpos($_enddate, '+')) . 'Z';
                if ($t_devices[0]->status == 'offline') {
                    $tp_devicepositions = $obj_traccar->positions($t_devices[0]->id);
                } else {
                    $tp_devicepositions = $obj_traccar->positions($t_devices[0]->id, $_startdate, $_enddate);
                }
                $tp_devicepositions = json_decode($tp_devicepositions->response);
                if (!empty($tp_devicepositions)) {
                    $tp_devices[0] = $tp_devicepositions[(count($tp_devicepositions) - 1)];

                    $datetime1 = $datetime2 = $interval = '';
                    for ($cnt = (count($tp_devicepositions) - 2); $cnt > 0; $cnt--) {
                        if ((isset($tp_devicepositions[$cnt]->attributes->motion)) && (isset($tp_devices[0]->attributes->motion))
                        ) {
                            if (
                                $tp_devicepositions[$cnt]->attributes->motion !=
                                $tp_devices[0]->attributes->motion
                            ) {
                                $datetime1 = new \DateTime($tp_devices[0]->fixTime);
                                $datetime2 = new \DateTime($tp_devicepositions[$cnt]->fixTime);
                                $interval = $datetime1->diff($datetime2);
                                $tmp_device_info['stop_movement_duration'] = '';
                                if(!empty($interval->format('%h'))){
                                    $tmp_device_info['stop_movement_duration'] .= $interval->format('%h') . " Hrs";
                                }elseif(!empty($interval->format('%i'))){
                                    $tmp_device_info['stop_movement_duration'] .= $interval->format('%i') . " Mins";
                                }
                                break;
                            } else {
                                $tmp_device_info['stop_movement_duration'] = 0;
                            }
                        }
                    }
                }
                
                // get last position data 
                $t_device_info = $obj_traccar->position($t_devices[0]->positionId);
                $t_device_info = json_decode($t_device_info->response);
                $t_device_info = $t_device_info[0];

                $device_state = ((isset($t_device_info->attributes->motion)) ? (($t_device_info->attributes->motion) ? 'Moving' : 'Stopped') : 'NA');

                $tmp_device_info['device_motion'] = $device_state;
                $tmp_device_info['device_speed'] = (isset($t_device_info->speed) ? $t_device_info->speed : 'NA');
                $tmp_device_info['device_acc'] = ((isset($t_device_info->attributes->ignition) ? (($t_device_info->attributes->ignition == true) ? 'On' : 'Off') : 'NA'));
                $tmp_device_info['device_battery'] = (isset($t_device_info->attributes->batteryLevel) ? $t_device_info->attributes->batteryLevel : ((isset($t_device_info->attributes->battery) ? $t_device_info->attributes->battery : 'NA')));
                $tmp_device_info['device_latlng'] = (isset($t_device_info->latitude) ? $t_device_info->latitude : '') . ',' . (isset($t_device_info->longitude) ? $t_device_info->longitude : '');
                $tmp_device_info['address'] = self::get_address((isset($t_device_info->latitude) ? $t_device_info->latitude : ''), (isset($t_device_info->longitude) ? $t_device_info->longitude : ''), 'RPT_GLS');
                // $tmp_device_info['address'] = '';
                // $positionTable = TableRegistry::get('tc_positions');

                array_push($device_info, $tmp_device_info);
                unset($tmp_device_info);
                $tmp_device_info = '';
                // }   //thi is temporary
            }
        }
        
        $this->set(compact('devices', 'device_info', 'start_date', 'end_date', 'device_targetname'));
        $this->set('_serialize', ['devices', 'device_info', 'start_date', 'end_date', 'device_targetname']);
    }
    public function gls()
    {
        
        $session = $this->request->session();
        $deviceTable = TableRegistry::get('Devices');

        $device_targetname = '';
        $device_info = array();
        $device_stops = array(); //[0 => ['date' => ''], 1 => ['date' => '']];
        $device_overspeed = array();
        $tmp_device_info = array(
            'device_targetname' => '', 'device_targetid' => '', 'device_stoppagetime' => '', 'device_mileage' => '', 'device_overspeed' => '',  'device_overspeedduration' => '',
            'device_id' => 0, 'start_distance' => 0, 'end_distance' => 0, 'overspeed' => 0, 'stops' => 0, 'acc' => 0
        );
        $start_date = $end_date = $init_time = $init_status = '';
        $device_info_cnt = 0;

        $to_date = AppController::convertutciso(date('d-m-Y H:i:s'));
        $from_date = AppController::convertutciso(date('d-m-Y H:i:s', (strtotime(date('d-m-Y H:i:s')) - 1 * 60)));

        if (($session->read('_user_type') == 2) || ($session->read('_user_type') == 3)) {
            $devices = $deviceTable->find('all', [])
                ->join([
                    'Deviceusers' => [
                        'table' => 'deviceusers',
                        'type' => 'LEFT',
                        'conditions' => 'Deviceusers.devices_id = Devices.id'
                    ]
                ])
                ->where(['Devices.device_status = 1'])
                ->andwhere(['Deviceusers.deviceusers_startdate <= "' . $from_date . '"'])
                ->andwhere(['Deviceusers.deviceusers_enddate >= "' . $to_date . '"'])
                ->andwhere(['Deviceusers.users_id ' => $session->read('_user_id')])
                // ->andwhere(['Devices.device_targetname' => $session->read('_device_targetname')]);
                ->toArray();
        } else {
            if($_GET['target_name']){
                if(!in_array('all', $_GET['target_name'])){
                    $devices = $deviceTable->find('all', [])
                        ->where(['Devices.device_status = 1'])
                        ->andwhere(['Devices.created_by ' => $session->read('_user_id')])
                        ->andwhere(['id IN' => $_GET['target_name']])
                        ->toArray();
                }else{
                    $devices = $deviceTable->find('all', [])
                        ->where(['Devices.device_status = 1'])
                        ->andwhere(['Devices.created_by ' => $session->read('_user_id')])
                        ->toArray();
                }                
            }else{
                $devices = $deviceTable->find('all', [])
                    ->where(['Devices.device_status = 1'])
                    ->andwhere(['Devices.created_by ' => $session->read('_user_id')])
                    ->toArray();
            }
            
        }

        $obj_traccar = new \App\Utility\Traccar();
        $t_login = $obj_traccar->login();

        if ($t_login->responseCode == 200) {
            foreach ($devices as $key => $value) {
                // if ($value->id == 1) {   // this is TEMPORARY
                $tmp_device_info = array(
                    'device_targetname' => '', 'device_targetid' => '', 'device_stoppagetime' => '', 'device_mileage' => '', 'device_overspeed' => '',  'device_overspeedduration' => '',
                    'device_id' => 0, 'start_distance' => 0, 'end_distance' => 0, 'overspeed' => 0, 'stops' => 0, 'acc' => 0
                );

                $tmp_device_info['device_targetname'] = $value->device_targetname;
                $tmp_device_info['device_targetid'] = $value->device_targetid;

                $tmp_device_info['device_stopmovement'] = '';
                $tmp_device_info['address'] = '';

                // get device info
                $t_devices = $obj_traccar->devices('id=' . $value->device_traccarid);
                $t_devices = json_decode($t_devices->response);
                
                $tmp_device_info['device_status'] = $t_devices[0]->status;

                $_startdate = date('Y-m-d H:i:s', strtotime('-4 days', strtotime(date('Y-m-d H:i:s'))));
                $_startdate = date('Y-m-d H:i:s', strtotime('-330 minutes', strtotime($_startdate)));
                $_enddate = date('Y-m-d H:i:s', strtotime('-330 minutes', strtotime(date('Y-m-d H:i:s'))));

                $_startdate = date('c', strtotime(date($_startdate)));
                $_enddate = date('c', strtotime(date($_enddate)));

                $_startdate = substr($_startdate, 0, strpos($_startdate, '+')) . 'Z';
                $_enddate = substr($_enddate, 0, strpos($_enddate, '+')) . 'Z';
                if ($t_devices[0]->status == 'offline') {
                    $tp_devicepositions = $obj_traccar->positions($t_devices[0]->id);
                } else {
                    $tp_devicepositions = $obj_traccar->positions($t_devices[0]->id, $_startdate, $_enddate);
                }
                $tp_devicepositions = json_decode($tp_devicepositions->response);
                if (!empty($tp_devicepositions)) {
                    $tp_devices[0] = $tp_devicepositions[(count($tp_devicepositions) - 1)];

                    $datetime1 = $datetime2 = $interval = '';
                    for ($cnt = (count($tp_devicepositions) - 2); $cnt > 0; $cnt--) {
                        if ((isset($tp_devicepositions[$cnt]->attributes->motion)) && (isset($tp_devices[0]->attributes->motion))
                        ) {
                            if (
                                $tp_devicepositions[$cnt]->attributes->motion !=
                                $tp_devices[0]->attributes->motion
                            ) {
                                $datetime1 = new \DateTime($tp_devices[0]->fixTime);
                                $datetime2 = new \DateTime($tp_devicepositions[$cnt]->fixTime);
                                $interval = $datetime1->diff($datetime2);
                                $tmp_device_info['stop_movement_duration'] = '';
                                if(!empty($interval->format('%h'))){
                                    $tmp_device_info['stop_movement_duration'] .= $interval->format('%h') . " Hrs";
                                }elseif(!empty($interval->format('%i'))){
                                    $tmp_device_info['stop_movement_duration'] .= $interval->format('%i') . " Mins";
                                }
                                break;
                            } else {
                                $tmp_device_info['stop_movement_duration'] = 0;
                            }
                        }
                    }
                }
                
                // get last position data 
                $t_device_info = $obj_traccar->position($t_devices[0]->positionId);
                $t_device_info = json_decode($t_device_info->response);
                $t_device_info = $t_device_info[0];

                $device_state = ((isset($t_device_info->attributes->motion)) ? (($t_device_info->attributes->motion) ? 'Moving' : 'Stopped') : 'NA');

                $tmp_device_info['device_motion'] = $device_state;
                $tmp_device_info['device_speed'] = (isset($t_device_info->speed) ? $t_device_info->speed : 'NA');
                $tmp_device_info['device_acc'] = ((isset($t_device_info->attributes->ignition) ? (($t_device_info->attributes->ignition == true) ? 'On' : 'Off') : 'NA'));
                $tmp_device_info['device_battery'] = (isset($t_device_info->attributes->batteryLevel) ? $t_device_info->attributes->batteryLevel : ((isset($t_device_info->attributes->battery) ? $t_device_info->attributes->battery : 'NA')));
                $tmp_device_info['device_latlng'] = (isset($t_device_info->latitude) ? $t_device_info->latitude : '') . ',' . (isset($t_device_info->longitude) ? $t_device_info->longitude : '');
                $tmp_device_info['address'] = self::get_address((isset($t_device_info->latitude) ? $t_device_info->latitude : ''), (isset($t_device_info->longitude) ? $t_device_info->longitude : ''), 'RPT_GLS');
                // $tmp_device_info['address'] = '';
                // $positionTable = TableRegistry::get('tc_positions');

                array_push($device_info, $tmp_device_info);
                unset($tmp_device_info);
                $tmp_device_info = '';
                // }   //thi is temporary
            }
        }
        
        $this->set(compact('devices', 'device_info', 'start_date', 'end_date', 'device_targetname'));
        $this->set('_serialize', ['devices', 'device_info', 'start_date', 'end_date', 'device_targetname']);
    }

    private function get_address($lat, $lng, $origin = '')
    {
        $session = $this->request->session();
        if (($lat != '') && ($lng != '')) {
            $url  = "https://maps.googleapis.com/maps/api/geocode/json?sensor=false&latlng=" . $lat . "," . $lng . "&key=AIzaSyCukSZQqUOJoCWLvpjx0trdWFV4FECXQfc";
            $json = @file_get_contents($url);
            $address = json_decode($json);
            if (empty($address->results)) {
                $this->log('|' . $origin . '|' . $session->read('_user_name') . '|' . $this->request->clientIp() . '|' . $json, 'info');
            } else {
                $this->log('|' . $origin . '|' . $session->read('_user_name') . '|' . $this->request->clientIp() . '|', 'info');
            }
            return (isset($address->results[0]->formatted_address) ? $address->results[0]->formatted_address : '');
        } else {
            return '';
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
                    ->contain(['Devicetypes', 'Devicegroups']);
            } else {
                $_devices = $deviceTable->find('all')
                    ->where(['Devices.device_status = 1']);
            }
        }

        $obj_traccar = new \App\Utility\Traccar();
        $t_login = $obj_traccar->login();
        $t_devices[][] = '';

        if ($t_login->responseCode == 200) {
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
        } else {
            $t_devices = '';
        }

        $devices = $this->paginate($_devices);
        $this->set(compact('devices', 't_devices'));
    }
}
