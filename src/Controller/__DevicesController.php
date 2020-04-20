<?php
namespace App\Controller;

use App\Controller\AppController;
use Cake\ORM\TableRegistry;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use App\Utility\Traccar;
/**
 * Devices Controller
 *
 * @property \App\Model\Table\DevicesTable $Devices
 *
 * @method \App\Model\Entity\Device[]|\Cake\Datasource\ResultSetInterface paginate($object = null, array $settings = [])
 */
class DevicesController extends AppController
{

    /**
     * Index method
     *
     * @return \Cake\Http\Response|void
     */
    public function index()
    {
        $session = $this->request->session();

        // debug($this->request);

        if ($this->request->is(['post', 'put'])) {
                // echo "1";

            $result_set = $this->Devices->find('all')
                            ->where(['Devices.device_targetid LIKE ' => '%'.$this->request->data('searchtext').'%']);
                            // ->orwhere();
                            // , ['conditions' =>
                            // [
                            //     'Devices.device_targetname LIKE ' => '%'.$this->request->data('searchtext').'%'
                            // ]]);

            // $result_set = $this->Devices->find('all', ['conditions' =>
            //                 [
            //                     'Devices.device_targetname LIKE ' => '%'.$this->request->data('searchtext').'%'
            //                 ]]);

            $devices = $this->paginate($result_set);

        }else if ($this->request->is('get')) {
                // echo "2";
        // }else{
                // echo "3";
            if (($session->read('_user_type') != 1) && ($session->read('_user_type') != 2)){
                $devices = $this->Devices->find('all')
                        ->join([
                            'Deviceusers' => [
                                'table' => 'deviceusers',
                                'type' => 'LEFT',
                                'conditions' => 'Deviceusers.devices_id = Devices.id'
                            ]
                        ])
                        ->where(['Devices.device_status = 1'])
                        ->andwhere(['Deviceusers.deviceusers_startdate <= NOW()'])
                        ->andwhere(['Deviceusers.deviceusers_enddate >= NOW()'])
                        ->andwhere(['Deviceusers.users_id ' => $session->read('_user_id')])
                        ->contain(['Devicetypes', 'Devicegroups']);

                $devices = $this->paginate($devices);
            }else{
                $this->paginate = [
                    'contain' => ['Devicetypes', 'Devicegroups']
                ];
                $devices = $this->paginate($this->Devices);
            }
        }

        $obj_traccar = new \App\Utility\Traccar();
        $t_login = $obj_traccar->login();

        if ($t_login->responseCode == 200){
            $t_devices = json_decode($obj_traccar->devices()->response, true);
        }else{
        }


        $this->set(compact('devices', 't_devices'));
    }

    /**
     * View method
     *
     * @param string|null $id Device id.
     * @return \Cake\Http\Response|void
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $device = $this->Devices->get($id, [
            'contain' => ['Devicetypes']
        ]);

        $this->set('device', $device);
    }

    public function singledevicetrack(){
        $session = $this->request->session();

        // $selected_device = $this->request->pass[0];

        if ($session->read('_device_targetname') != ''){
                // $device = TableRegistry::get('Devices');
                // $_devices = $this->Devices->find('all')
                //             ->select(['Devices.id', 'Devices.device_traccarid',
                //                         'Devices.device_targetname', 'Devices.device_identifier', 'Devices.device_icon'])
                //             ->andwhere(['Devices.device_targetname LIKE ' => '%'.$session->read('_device_targetname').'%'])
                //             ->andwhere(['Devices.device_status = 1'])
                //             ->toArray();
                if (($session->read('_user_type') == 1) || ($session->read('_user_type') == 5)){
                    $_devices = $this->Devices->find('all')
                                ->select(['DISTINCT Devices.id', 'Devices.device_traccarid',
                                            'Devices.device_targetname', 'Devices.device_targetid',
                                            'Devices.device_identifier', 'Devices.device_icon',
                                            'Devices.device_overspeed'])
                                // ->join([
                                //     'Deviceusers' => [
                                //         'table' => 'deviceusers',
                                //         'type' => 'LEFT',
                                //         'conditions' => 'Deviceusers.devices_id = Devices.id'
                                //     ]
                                // ])
                                // ->andwhere(['Deviceusers.deviceusers_startdate <= NOW()'])
                                // ->andwhere(['Deviceusers.deviceusers_enddate >= NOW()'])
                                // ->andwhere(['Deviceusers.users_id ' => $session->read('_user_id')])
                                // ->andwhere(['Devices.device_targetname LIKE ' => $session->read('_device_targetname')])
                                ->andwhere(['Devices.device_targetname LIKE ' => '%'.$session->read('_device_targetname').'%'])
                                ->andwhere(['Devices.device_status = 1'])
                                ->toArray();
                }else{
                    $_devices = $this->Devices->find('all')
                                ->select(['DISTINCT Devices.id', 'Devices.device_traccarid',
                                            'Devices.device_targetname',  'Devices.device_targetid',
                                            'Devices.device_identifier', 'Devices.device_icon',
                                            'Devices.device_overspeed'])
                                ->join([
                                    'Deviceusers' => [
                                        'table' => 'deviceusers',
                                        'type' => 'LEFT',
                                        'conditions' => 'Deviceusers.devices_id = Devices.id'
                                    ]
                                ])
                                ->andwhere(['Deviceusers.deviceusers_startdate <= ' => date('Y-m-d H:i:s')])
                                ->andwhere(['Deviceusers.deviceusers_enddate >= ' => date('Y-m-d H:i:s')])
                                ->andwhere(['Deviceusers.users_id ' => $session->read('_user_id')])
                                // ->andwhere(['Devices.device_targetname LIKE ' => $session->read('_device_targetname')])
                                ->andwhere(['Devices.device_targetname LIKE ' => '%'.$session->read('_device_targetname').'%'])
                                ->andwhere(['Devices.device_status = 1'])
                                ->toArray();
                }

                $devices = '';

                if (empty($_devices)){
                    $this->Flash->error(__('Please select a device first, you have access to.', 'Device'));
                    $this->redirect(['controller'=>'Dashboards', 'action' => 'managementdashboard']);
                }else{
                    foreach ($_devices AS $key => $val){
                        $devices .= $val->device_traccarid.'|'.$val->device_targetname.'|'.$val->device_identifier.'|'.$val->device_targetid.'|'.$val->device_overspeed.',';
                    }
                }

        }else{
            $this->Flash->error(__('Please select a device first.', 'Device'));
            $this->redirect(['controller'=>'Dashboards', 'action' => 'managementdashboard']);
        }
        $this->set(compact('devices', 'selected_device', '_devices'));
    }

    public function trackdevice($device_id = null){

        $session = $this->request->session();
        $device_init_info = $start_date = $end_date = '';

        if ($session->read('_device_targetname') != ''){

            $obj_traccar = new \App\Utility\Traccar();
            $t_login = $obj_traccar->login();

            if ($this->request->is('post') || $this->request->is('get')) {
                $_device = $this->Devices->find('all')
                            ->select(['Devices.id', 'Devices.device_traccarid',
                                        'Devices.device_targetname', 'Devices.device_identifier'])
                            ->where(['Devices.device_status = 1'])
                            ->andwhere(['Devices.device_targetname' => $session->read('_device_targetname')])
                            ->toArray();

                $device_id = $_device[0]['device_traccarid'];
                if (isset($this->request->data['startdate'])){
                    $start_date = $this->request->data['startdate'];
                    $end_date = $this->request->data['enddate'];
                    // $start_time = $this->request->data['start_time'];
                    // $end_time = $this->request->data['end_time'];
                }else{
                    $start_date = date('Y-m-d').' 00:00';
                    $end_date = date('Y-m-d').' 23:59';
                    // $start_time = '00:00';
                    // $end_time = '23:59';
                }

                $from_date = date('c', strtotime(date($start_date)));
                $to_date = date('c', strtotime(date($end_date)));

                $from_date = substr($from_date, 0, strpos($from_date, '+')).'Z';
                $to_date = substr($to_date, 0, strpos($to_date, '+')).'Z';

                if ($t_login->responseCode == 200){
                    $t_devices = $obj_traccar->positions($device_id, $from_date, $to_date);
                    $device_init_info = json_decode($t_devices->response);
                    if (empty($device_init_info)){
                        // $device_init_info = $device_init_info[(count($device_init_info) - 1)];
                    // }else{
                        $this->Flash->error(__('No location data found.', 'Device'));
                        $device_init_info = 0;
                    }else{
                        $device_init_info = json_encode($device_init_info);
                    }
                }else{
                }
            }

            if ($session->read('_user_type') > 2){
                $devices = $this->Devices->find('all', [
                            // 'keyField' => 'device_traccarid'
                        ])
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
                        // ->andwhere(['Deviceusers.deviceusers_startdate <= NOW()'])
                        // ->andwhere(['Deviceusers.deviceusers_enddate >= NOW()'])
                        ->andwhere(['Deviceusers.users_id ' => $session->read('_user_id')])
                        ->andwhere(['Devices.device_targetname' => $session->read('_device_targetname')])
                        ->toArray();
            }else{
                $devices = $this->Devices->find('all', [
                            // 'keyField' => 'device_traccarid'
                        ])
                        ->where(['Devices.device_status = 1'])
                        ->andwhere(['Devices.device_targetname' => $session->read('_device_targetname')])
                        ->toArray();
            }

        }else{
            $this->Flash->error(__('Please select a device first.', 'Device'));

            if (($session->read('_user_type') == 1) || ($session->read('_user_type') == 2)){
                $this->redirect(['controller'=>'Dashboards', 'action' => 'managementdashboard']);
            }elseif ($session->read('_user_type') == 3){
                $this->redirect(['controller'=>'Dashboards', 'action' => 'userdashboard']);
            }elseif ($session->read('_user_type') == 4){
                $this->redirect(['controller'=>'Dashboards', 'action' => 'regulardashboard']);
            }
        }

        if ($start_date == ''){
            $start_date = $end_date = date('Y-m-d');
        }
        $this->set(compact('device_id', 'devices', 'device_init_info', 'start_date', 'end_date', 'start_time', 'end_time'));
    }

    // auto complete return
    public function ajaxgetdevicetargetnames(){
        $target_id = $this->request->pass[0];
        $session = $this->request->session();
        if ($session->read('_user_type') == 1){
            $devices = $this->Devices->find('all')
                    ->select(['Devices.device_targetname'])
                    ->where(['Devices.device_targetname LIKE "%'.$target_id.'%"'])
                    ->andwhere(['Devices.device_status = 1']);
        }else{
            $devices = $this->Devices->find('all')
                    ->select(['Devices.device_targetname'])
                    ->join([
                            'cdeviceusers' => [
                                'table' => 'deviceusers',
                                'type' => 'LEFT',
                                'conditions' => 'Devices.id = cdeviceusers.devices_id'
                            ]])
                    ->join([
                            'cusers' => [
                                'table' => 'users',
                                'type' => 'LEFT',
                                'conditions' => 'cdeviceusers.users_id = cusers.id'
                            ]])
                    ->where(['Devices.device_targetname LIKE "%'.$target_id.'%"'])
                    ->andwhere(['Devices.device_status = 1'])
                    // ->andwhere(['cdeviceusers.deviceusers_startdate < ' => date('Y-m-d H:i:s')])
                    // ->andwhere(['cdeviceusers.deviceusers_enddate > ' => date('Y-m-d H:i:s')])
                    ->andwhere(['cusers.id ' => $session->read('_user_id')])
                    ->andwhere(['cdeviceusers.deviceusers_status ' => 1])
                    ->group(['Devices.device_targetname']);
        }

      echo json_encode($devices);
      die();
    }

    // set select device in session
    public function ajaxsetdevicetargetsetname(){
        $session = $this->request->session();
        $session->write('_device_targetname', $this->request->data['device_targetname']);
        echo $session->read('_device_targetname');
        die();
    }


    public function ajaxgetdevicepositions(){
        $session = $this->request->session();

        $obj_traccar = new \App\Utility\Traccar();
        $t_login = $obj_traccar->login();

        if ($session->read('_user_type') == 1){
            $_device = $this->Devices->find('all')
                        ->select(['DISTINCT Devices.id', 'Devices.device_traccarid',
                                    'Devices.device_targetname', 'Devices.device_identifier'])
                        ->where(['Devices.device_status = 1'])
                        ->andwhere(['Devices.device_traccarid = '.$this->request->data['device_id']])
                        ->contain(['Devicetypes'])
                        ->toArray();
        }else if ($session->read('_user_type') == 5){
            $_device = $this->Devices->find('all')
                        ->select(['DISTINCT Devices.id', 'Devices.device_traccarid',
                                    'Devices.device_targetname', 'Devices.device_identifier'])
                        ->where(['Devices.device_status = 1'])
                        ->andwhere(['Devices.device_traccarid = '.$this->request->data['device_id']])
                        ->contain(['Devicetypes'])
                        ->toArray();
        }else{
            $_device = $this->Devices->find('all')
                        ->select(['DISTINCT Devices.id', 'Devices.device_traccarid',
                                    'Devices.device_targetname', 'Devices.device_identifier'])
                        ->join([
                                'cdeviceusers' => [
                                    'table' => 'deviceusers',
                                    'type' => 'LEFT',
                                    'conditions' => 'Devices.id = cdeviceusers.devices_id'
                                ]])
                        ->join([
                                'cusers' => [
                                    'table' => 'users',
                                    'type' => 'LEFT',
                                    'conditions' => 'cdeviceusers.users_id = cusers.id'
                                ]])
                        ->where(['Devices.device_status = 1'])
                        ->andwhere(['Devices.device_traccarid = '.$this->request->data['device_id']])
                        ->andwhere(['cdeviceusers.users_id ' => $session->read('_user_id')])
                        ->andwhere(['cdeviceusers.deviceusers_startdate <= ' => date('Y-m-d H:i:s')])
                        ->andwhere(['cdeviceusers.deviceusers_enddate >= ' => date('Y-m-d H:i:s')])
                        ->contain(['Devicetypes'])
                        ->toArray();
        }

        if (!empty($_device)){
            // $traccar_conn = ConnectionManager::get('db_traccar'); #Remote Traccar Database
            // $sql   = "SELECT * FROM  positions WHERE id = ".$this->request->data['device_id'];
            // $query = $traccar_conn->prepare($sql);
            // $query->execute();
            // $result = $query->fetchAll(); #Here is the result
            //
            // debug($result);

    //        $from_date = date('c', strtotime(date('Y-m-d 00:00:00')));
    //        $to_date = date('c', strtotime(date('Y-m-d H:i:s')));

    //        $from_date = date('c', strtotime('2018-03-10 00:00:00'));
    //        $to_date = date('c', strtotime('2018-03-10 23:59:59'));


            if ($this->request->data['end_date'] != ''){
                $to_date = date('c', strtotime(date($this->request->data['end_date'].' '.(isset($this->request->data['end_time'])?$this->request->data['end_time']:'00:00'))));
            }else{
                $to_date = date('c', strtotime(date('Y-m-d '.(isset($this->request->data['end_time'])?$this->request->data['end_time']:'23:59'))));
            }
            if ($this->request->data['start_date'] != ''){
                $from_date = date('c', strtotime(date($this->request->data['start_date'].' '.(isset($this->request->data['start_time'])?$this->request->data['start_time']:'00:00'))));
                $from_date = substr($from_date, 0, strpos($from_date, '+')).'Z';
                $to_date = substr($to_date, 0, strpos($to_date, '+')).'Z';
            }else{
                // $from_date = date('c', strtotime(date('Y-m-d '.(isset($this->request->data['start_time'])?$this->request->data['start_time']:'00:00'))));
            }

            // echo $from_date."<br>";
            // echo $to_date."<br>";

            // debug($this->request->data['device_id']);


            if ($t_login->responseCode == 200){

                $t_devices = '';

                if ($this->request->data['start_date'] != ''){
                    $t_devices = $obj_traccar->positions($this->request->data['device_id'], $from_date, $to_date);
                }else{
                    $t_devices = $obj_traccar->positions($this->request->data['device_id']);//, $from_date, $to_date);
                }

                $position_array = json_decode($t_devices->response);

                if ($this->request->data['position_id'] != ''){
                    // debug($position_array);
                    if (array_key_exists($this->request->data['position_id'], $position_array)){
                        $position_array[$this->request->data['position_id']]->deviceName = $_device[0]['device_targetname'];
                        $position_array[$key]->speed = ($position_array[$key]->speed * 1.852);
                        $position_array[$this->request->data['position_id']]->deviceTime = date('Y-m-d H:i:s', strtotime($position_array[$key]->deviceTime));
                        echo json_encode($position_array[$this->request->data['position_id']]);
                    }else{
                        echo json_encode(false);
                    }
                }else{
                   // debug($position_array);
                    if (!empty($position_array)){
                        foreach ($position_array as $key => $value) {
                            if ($value->deviceId == $this->request->data['device_id']){
                                $position_array[$key]->deviceName = $_device[0]['device_targetname'];
                                $position_array[$key]->speed = ($position_array[$key]->speed * 1.852);
                                $position_array[$key]->deviceTime = date('Y-m-d H:i:s', strtotime($position_array[$key]->deviceTime));
                                echo json_encode($position_array[$key]);
                                break;
                            }
                        }
                    }
                    // else{
                    //     echo json_encode(false);
                    // }
                }

            }else{
            }
        }else{
            echo json_encode(false);
        }
        die();
    }

    /**
     * Add method
     *
     * @return \Cake\Network\Response|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
//        $_branches_perm = Configure::read('_user_perm.'.$session->read('_user_level').'.branches');
//
//        if (array_search("index", $_branches_perm) === false){
//                $this->Flash->error(__('Insufficient permission, cannot access that page.'));
//                return $this->redirect(
//                ['controller' => 'Registrations', 'action' => 'index']
//            );
//        }

        $session = $this->request->session();

        if (($session->read('_user_type') == 2) || ($session->read('_user_type') == 3)){
            $this->Flash->error(__('Insufficient permission, cannot access that page.'));
                return $this->redirect(
                ['controller' => 'Devices', 'action' => 'index']
            );
        }

        $device = $this->Devices->newEntity();
        if ($this->request->is('post')) {

            $target_id = '';
            if ($this->request->data['devicetypes_id'] == 1){
                $target_id = 'P';
            }else if ($this->request->data['devicetypes_id'] == 2){
                $target_id = 'D';
            }

            if ($this->request->data['device_source'] == 'Internal'){
                $target_id .= 'I';
            }else if ($this->request->data['device_source'] == 'External'){
                $target_id .= 'E';
            }

            $_devicecount = $this->Devices->find()
                        ->select(['Devices.id'])
                        ->where(['created LIKE "'.date('Y-m-d').'%"'])
                        ->andwhere(['created_by' => $session->read('_user_id')])
                        ->count();

            $_devicecount = $_devicecount + 1;
            $_devicecount = sprintf("%02d", ($_devicecount));

            $target_id .= strtoupper(substr($session->read('_user_fname'), 0, 1)).strtoupper(substr($session->read('_user_lname'), 0, 1)).date('ymd').$_devicecount;


            // if (trim($this->request->data['device_targetname']) == ''){
                $this->request->data['device_targetname'] = $target_id;
            // }

            $obj_traccar = new \App\Utility\Traccar();
            $t_login = $obj_traccar->login();

            $t_adddevice = $obj_traccar->addDevice($this->request->data['device_targetname'], $this->request->data['device_identifier']);
            $t_adddevice_arr = json_decode($t_adddevice->response, true);

//            200 - ok
//            400 - duplicate identifier

            if ($t_adddevice->responseCode == 200){
                $this->request->data['device_traccarid'] = $t_adddevice_arr['id'];
                $this->request->data['device_targetid'] = $target_id;

                $device = $this->Devices->patchEntity($device, $this->request->data);
                $_device_result = $this->Devices->save($device);

                if ($_device_result){

                    $_device = $this->Devices->get($_device_result->id, [
                        'contain' => []
                    ]);

                    $_device->device_targetid = $target_id; //.$_device_result->id;

                    if ($this->Devices->save($_device)){
                        $this->Flash->success(__('The {0} has been saved.', 'Device'));
                        return $this->redirect(['action' => 'index']);
                    }else{
                        $t_deldevice = $obj_traccar->deleteDevice($t_adddevice_arr['id'], $this->request->data['device_targetname'], $this->request->data['device_identifier']);
                        $this->Flash->error(__('Failed to generate Target ID for {0}. Please contacat administrator', 'Device'));
                        return $this->redirect(['action' => 'index']);
                    }

                }else{
                    $t_deldevice = $obj_traccar->deleteDevice($t_adddevice_arr['id'], $this->request->data['device_targetname'], $this->request->data['device_identifier']);
                    $this->Flash->error(__('Device Could Not Be Added. Please Contact Administrator', 'Device'));
                }

            }else if ($t_adddevice->responseCode == 400){
                $this->Flash->error(__('Device Could Not Be Added. Identifier Already Esists', 'Device'));
            }

        }
        $devicetypes = $this->Devices->Devicetypes->find('list', ['limit' => 200]);
        $devicegroups = $this->Devices->Devicegroups->find('list', ['limit' => 200]);
        $this->set(compact('device', 'devicetypes', 'devicegroups'));
        $this->set('_serialize', ['device']);
    }

    /**
     * Edit method
     *
     * @param string|null $id Device id.
     * @return \Cake\Network\Response|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Network\Exception\NotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $session = $this->request->session();

        if (($session->read('_user_type') == 2) || ($session->read('_user_type') == 3)){
            $this->Flash->error(__('Insufficient permission, cannot access that page.'));
                return $this->redirect(
                ['controller' => 'Deviced', 'action' => 'index']
            );
        }

        $device = $this->Devices->get($id, [
            'contain' => []
        ]);
        if ($this->request->is(['patch', 'post', 'put'])) {

            if (($this->request->data['device_registrationdate_org'] == '') &&
                ($this->request->data['device_status'] == 1)){
                $this->request->data['device_registrationdate'] = date('Y-m-d');
            }

            $device = $this->Devices->patchEntity($device, $this->request->data);

            $obj_traccar = new \App\Utility\Traccar();
            $t_login = $obj_traccar->login();
            $t_updtdevice = $obj_traccar->editDevice($this->request->data['device_traccarid'], $this->request->data['device_targetname'], $this->request->data['device_identifier']);
            $t_updtdevice_arr = json_decode($t_updtdevice->response, true);

            if ($t_updtdevice->responseCode == 200){
                if ($this->Devices->save($device)) {
                    $this->Flash->success(__('The {0} has been saved.', 'Device'));
                    return $this->redirect(['action' => 'index']);
                } else {
                    $this->Flash->error(__('The {0} could not be saved. Please, try again.', 'Device'));
                }
            }else{
                $this->Flash->error(__('The {0} could not be updated on Traccar. Please, try again.', 'Device'));
            }
        }
        $devicetypes = $this->Devices->Devicetypes->find('list', ['limit' => 200]);
        $devicegroups = $this->Devices->Devicegroups->find('list', ['limit' => 200]);
        $this->set(compact('device', 'devicetypes', 'devicegroups'));
        $this->set('_serialize', ['device']);
    }

    /**
     * Delete method
     *
     * @param string|null $id Device id.
     * @return \Cake\Network\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $session = $this->request->session();

        if (($session->read('_user_type') == 3) || ($session->read('_user_type') == 4)){
            $this->Flash->error(__('Insufficient permission, cannot access that page.'));
                return $this->redirect(
                ['controller' => 'Deviced', 'action' => 'index']
            );
        }
        $this->request->allowMethod(['post', 'delete']);
        $device = $this->Devices->get($id);
        if ($this->Devices->delete($device)) {
            $this->Flash->success(__('The {0} has been deleted.', 'Device'));
        } else {
            $this->Flash->error(__('The {0} could not be deleted. Please, try again.', 'Device'));
        }
        return $this->redirect(['action' => 'index']);
    }
}
