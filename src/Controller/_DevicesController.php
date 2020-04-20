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
        
        $obj_traccar = new \App\Utility\Traccar();
        $t_login = $obj_traccar->login();
        
        if ($t_login->responseCode == 200){
            $t_devices = json_decode($obj_traccar->devices()->response, true);
        }else{
        }
        
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
        
        $selected_device = $this->request->pass[0];
        
//        $device = TableRegistry::get('Devices');
        $_devices = $device->find('all')
                    ->select(['Devices.id', 'Devices.device_traccarid', 
                                'Devices.device_targetname', 'Devices.device_identifier'])
                    ->where(['Devices.device_status = 1'])->toArray();

        $devices = '';

        foreach ($_devices AS $key => $val){
            $devices .= $val->device_traccarid.'|'.$val->device_targetname.'|'.$val->device_identifier.',';
        }
        
        $this->set(compact('devices', 'selected_device'));
    }
    
    public function trackdevice($device_id = null){
        
        $session = $this->request->session();
        
        $device_init_info = $start_date = $end_date = '';
        
        if ($this->request->is('post') || $this->request->is('get')) {
            
            if (isset($this->request->data['devices_id'])){
                $device_id = $this->request->data['devices_id'];
                $start_date = $this->request->data['start_date'];
                $end_date = $this->request->data['end_date'];
                $start_time = $this->request->data['start_time'];
                $end_time = $this->request->data['end_time'];
            }else if (isset($this->request->params['pass'][0])){
                $device_id = $this->request->params['pass'][0];
                $start_date = date('Y-m-d');
                $end_date = date('Y-m-d');
                $start_time = '00:00';
                $end_time = '23:59';
            }else{
                $this->redirect(['controller'=>'Dashboards', 'action' => 'managementdashboard']);
            }
            
            $from_date = date('c', strtotime(date($start_date.' 00:00:00')));
            $to_date = date('c', strtotime(date($end_date.' 23:59:59')));
            
            $obj_traccar = new \App\Utility\Traccar();
            $t_login = $obj_traccar->login();
//
//
//            $from_date = date('c', strtotime('2018-03-09 00:00:00'));
//            $to_date = date('c', strtotime('2018-03-19 23:59:59'));

//            $from_date = date('c', strtotime('2018-03-10 00:00:00'));
//            $to_date = date('c', strtotime('2018-03-10 23:59:59'));
        
            $from_date = substr($from_date, 0, strpos($from_date, '+'));
            $to_date = substr($to_date, 0, strpos($to_date, '+'));
            
//            debug($device_id);
//            debug($from_date);
//            debug($to_date);
            
            
            if ($t_login->responseCode == 200){
                $t_devices = $obj_traccar->positions($device_id, $from_date, $to_date);
//                debug($t_devices);
                $device_init_info = json_decode($t_devices->response);
                if (!empty($device_init_info)){
                    $device_init_info = $device_init_info[(count($device_init_info) - 1)];
                }else{
                    $this->Flash->error(__('No location data found.', 'Device'));
                }
            }else{
            }
        }else{
            if (($session->read('_user_type') == 1) || ($session->read('_user_type') == 2)){
//                return $this->redirect(['action' => 'managementdashboard']);
                $this->redirect(['controller'=>'Dashboards', 'action' => 'managementdashboard']);
            }elseif ($session->read('_user_type') == 3){
//                return $this->redirect(['action' => 'userdashboard']);
                $this->redirect(['controller'=>'Dashboards', 'action' => 'userdashboard']);
            }elseif ($session->read('_user_type') == 4){
//                return $this->redirect(['action' => 'regulardashboard']);
                $this->redirect(['controller'=>'Dashboards', 'action' => 'regulardashboard']);
            }
        }
//        $devices = $this->Devices->find('list', ['limit' => 200, 'keyField' => 'device_traccarid',
//                                'valueField' => 'device_targetid']);
        
        if ($session->read('_user_type') > 2){
            $devices = $this->Devices->find('list', [
                        'keyField' => 'device_traccarid'
                    ])
                    ->select(['Devices.id', 'Devices.device_traccarid', 
                                'Devices.device_targetname', 'Devices.device_identifier'])
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
                    ->toArray();
        }else{
            $devices = $this->Devices->find('list', [
                        'keyField' => 'device_traccarid'
                    ])
                    ->select(['Devices.id', 'Devices.device_traccarid', 
                                'Devices.device_targetname', 'Devices.device_identifier'])
                    ->where(['Devices.device_status = 1'])
                    ->toArray();
        }
        if ($start_date == ''){
            $start_date = $end_date = date('Y-m-d');
        }
        $this->set(compact('device_id', 'devices', 'device_init_info', 'start_date', 'end_date', 'start_time', 'end_time'));
    }
    
    public function ajaxgetdevicepositions(){
        $obj_traccar = new \App\Utility\Traccar();
        $t_login = $obj_traccar->login();
        
        $_device = $this->Devices->find('all')
                    ->select(['Devices.id', 'Devices.device_traccarid', 
                                'Devices.device_targetname', 'Devices.device_identifier'])
                    ->where(['Devices.device_status = 1'])
                    ->andwhere(['Devices.device_traccarid = '.$this->request->data['device_id']])
                    ->contain(['Devicetypes'])
                    ->toArray();
        
//        $from_date = date('c', strtotime(date('Y-m-d 00:00:00')));
//        $to_date = date('c', strtotime(date('Y-m-d H:i:s')));
        
//        $from_date = date('c', strtotime('2018-03-10 00:00:00'));
//        $to_date = date('c', strtotime('2018-03-10 23:59:59'));
        
        if ($this->request->data['start_date'] != ''){
            $from_date = date('c', strtotime(date($this->request->data['start_date'].' '.(isset($this->request->data['start_time'])?$this->request->data['start_time']:'00:00'))));
        }else{
            $from_date = date('c', strtotime(date('Y-m-d '.(isset($this->request->data['start_time'])?$this->request->data['start_time']:'00:00'))));
        }
        if ($this->request->data['end_date'] != ''){
            $to_date = date('c', strtotime(date($this->request->data['end_date'].' '.(isset($this->request->data['end_time'])?$this->request->data['end_time']:'00:00'))));
        }else{
            $to_date = date('c', strtotime(date('Y-m-d '.(isset($this->request->data['end_time'])?$this->request->data['end_time']:'23:59'))));
        }
        
        $from_date = substr($from_date, 0, strpos($from_date, '+')).'Z';
        $to_date = substr($to_date, 0, strpos($to_date, '+')).'Z';
        
//        echo $from_date."<br>";
//        echo $to_date."<br>";
        
        if ($t_login->responseCode == 200){
            $t_devices = $obj_traccar->positions($this->request->data['device_id'], $from_date, $to_date);
//            debug($t_devices);
//            debug(json_decode($t_devices->response));
            
            $position_array = json_decode($t_devices->response);
            
            if ($this->request->data['position_id'] != ''){
                if (array_key_exists($this->request->data['position_id'], $position_array)){
                    $position_array[$this->request->data['position_id']]->deviceName = $_device[0]['device_targetname'];
                    echo json_encode($position_array[$this->request->data['position_id']]);
                }else{
                    echo json_encode(false);
                }
            }else{
//                debug($position_array);
                if (!empty($position_array)){
                    $position_array[(count($position_array) - 1)]->deviceName = $_device[0]['device_targetname'];
                    echo json_encode($position_array[(count($position_array) - 1)]);
                }else{
                    echo json_encode(false);
                }
            }
            
        }else{
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
        
        if (($session->read('_user_type') == 3) || ($session->read('_user_type') == 4)){
            $this->Flash->error(__('Insufficient permission, cannot access that page.'));
                return $this->redirect(
                ['controller' => 'Devices', 'action' => 'index']
            );
        }
        
        $device = $this->Devices->newEntity();
        if ($this->request->is('post')) {
            
//            debug($this->request->data);
            
            $target_id = '';
            if ($this->request->data['devicetypes_id'] == 1){
                $target_id = 'PX'.$session->read('_user_shortname').date('Ymd');
            }else if ($this->request->data['devicetypes_id'] == 2){
                $target_id = 'DX'.$session->read('_user_shortname').date('Ymd');
            }
            
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
                
                    $_device->device_targetid = $target_id.$_device_result->id;
                    
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
        
        if (($session->read('_user_type') == 3) || ($session->read('_user_type') == 4)){
            $this->Flash->error(__('Insufficient permission, cannot access that page.'));
                return $this->redirect(
                ['controller' => 'Deviced', 'action' => 'index']
            );
        }
        
        $device = $this->Devices->get($id, [
            'contain' => []
        ]);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $device = $this->Devices->patchEntity($device, $this->request->data);
            if ($this->Devices->save($device)) {
                $this->Flash->success(__('The {0} has been saved.', 'Device'));
                return $this->redirect(['action' => 'index']);
            } else {
                $this->Flash->error(__('The {0} could not be saved. Please, try again.', 'Device'));
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
