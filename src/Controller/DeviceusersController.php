<?php

namespace App\Controller;

use App\Controller\AppController;
use Cake\ORM\TableRegistry;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\Routing\Router;

/**
 * Deviceusers Controller
 *
 * @property \App\Model\Table\DeviceusersTable $Deviceusers
 *
 * @method \App\Model\Entity\Deviceuser[]|\Cake\Datasource\ResultSetInterface paginate($object = null, array $settings = [])
 */
class DeviceusersController extends AppController
{

    /**
     * Index method
     *
     * @return \Cake\Http\Response|void
     */
    public function index()
    {
        $session = $this->request->session();

        $this->_conditions = array();
        if ($session->check('conditions')) {
            $this->_conditions = $session->read('conditions');
        }

        if ($this->request->is(['post', 'put'])) {
            // debug('POST');
            $conditions = '(Users.user_shortname LIKE "%' . $this->request->data('searchtext') . '%" OR ' .
                'Users.user_name LIKE "%' . $this->request->data('searchtext') . '%" OR 
            Devices.device_targetid LIKE "%' . $this->request->data('searchtext') . '%")' . (($this->request->data('searchtext') == '') ? (' AND Deviceusers.deviceusers_status = 1') : '');

            $this->_conditions = array($conditions);
            $session->write('conditions', $this->_conditions);
            $session->write('searchtext', $this->request->data('searchtext'));
            $this->paginate = [
                'fields' => [
                    'Devices.device_targetid', 'Users.user_name', 'Deviceusers.deviceusers_startdate',
                    'Deviceusers.deviceusers_enddate', 'Deviceusers.deviceusers_status', 'Deviceusers.id'
                ],
                'conditions' => $this->_conditions,
                'contain' => ['Devices', 'Users'],
            ];
        } else {
            // debug('GET');
            $this->Deviceusers->recursive = 0;
            if ($this->_conditions != '') {
                $this->paginate = [
                    'conditions' => $this->_conditions,
                    'contain' => ['Devices', 'Users'],
                ];
            } else {
                $this->paginate = [
                    'conditions' => 'Deviceusers.deviceusers_status = 1',
                    'contain' => ['Devices', 'Users'],
                ];
            }
        }
        $deviceusers = $this->paginate();
        $this->set(compact('deviceusers'));
    }

    /**
     * View method
     *
     * @param string|null $id Deviceuser id.
     * @return \Cake\Http\Response|void
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $deviceuser = $this->Deviceusers->get($id, [
            'contain' => ['Devices', 'Users']
        ]);

        $this->set('deviceuser', $deviceuser);
    }

    /**
     * Add method
     *
     * @return \Cake\Network\Response|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $session = $this->request->session();

        if (($session->read('_user_type') == 3)) {
            $this->Flash->error(__('Insufficient permission, cannot access that page.'));
            return $this->redirect(
                ['controller' => 'Dashboards', 'action' => 'index']
            );
        }

        $deviceuser = $this->Deviceusers->newEntity();
        if ($this->request->is('post')) {

            // if ($this->request->data['deviceuser'] > 1){
            $deviceuser = $this->Deviceusers->newEntities($this->request->data['deviceuser']);
            // $deviceuser = $this->Deviceusers->patchEntity($deviceuser, $this->request->data['deviceuser']);
            if ($this->Deviceusers->saveMany($deviceuser)) {
                $this->Flash->success(__('The {0} has been saved.', 'Deviceuser'));
                return $this->redirect(['action' => 'index']);
            } else {
                debug($deviceuser);
                $this->Flash->error(__('The {0} could not be saved. Please, try again.', 'Deviceuser'));
            }
            // }else{
            //     $deviceuser = $this->Deviceusers->patchEntity($deviceuser, $this->request->data['deviceuser'][0]);
            //     if ($this->Deviceusers->save($deviceuser)) {
            //         $this->Flash->success(__('The {0} has been saved.', 'Deviceuser'));
            //         return $this->redirect(['action' => 'index']);
            //     } else {
            //         debug($deviceuser);
            //         $this->Flash->error(__('The {0} could not be saved. Please, try again.', 'Deviceuser'));
            //     }
            // }

        }

        if (($session->read('_user_type') == 2)) {
            $device = TableRegistry::get('Devices');
            $devices = $device->find('list')
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
                ->andwhere(['Deviceusers.users_id ' => $session->read('_user_id')])
                ->toArray();

            $users = TableRegistry::get('Users');
            $users  = $users->find('list', [
                'limit' => 200,
                'valueField' => function ($row) {
                    return $row['user_shortname'];
                }
            ])
                ->contain(['Usertypes', 'ParentUsers', 'Companies', 'States'])
                ->where(['Usertypes.usertype_level >' => $session->read('_user_type_level')]);
        } else {
            $devices = $this->Deviceusers->Devices->find('list', ['limit' => 900]);
            // $users = $this->Deviceusers->Users->find('list');
            $users = $this->Deviceusers->Users->find('list', [
                'limit' => 900,
                'valueField' => function ($row) {
                    return $row['user_shortname'];
                }
            ]);
        }

        $this->set(compact('deviceuser', 'devices', 'users'));
        $this->set('_serialize', ['deviceuser']);
    }

    /**
     * Edit method
     *
     * @param string|null $id Deviceuser id.
     * @return \Cake\Network\Response|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Network\Exception\NotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $session = $this->request->session();

        if ((!$session->check('_referer')) || ($session->read('_referer') == '')) {
            $session->write('_referer', $this->referer());
            if (strpos($session->read('_referer'), '?') === false) {
                $session->write('_referer', '');
            }
        }

        if (($session->read('_user_type') == 4)) {
            $this->Flash->error(__('Insufficient permission, cannot access that page.'));
            return $this->redirect(
                ['controller' => 'Dashboards', 'action' => 'index']
            );
        }

        $deviceuser = $this->Deviceusers->get($id, [
            'contain' => []
        ]);
        if ($this->request->is(['patch', 'post', 'put'])) {

            // $this->request->data['deviceusers_startdate'] = $this->request->data['deviceusers_startdate'].' '.$this->request->data['deviceusers_starttime'];
            // $this->request->data['deviceusers_enddate'] = $this->request->data['deviceusers_enddate'].' '.$this->request->data['deviceusers_endtime'];
            $this->request->data['deviceusers_status'] = 1;
            $deviceuser = $this->Deviceusers->patchEntity($deviceuser, $this->request->data);
            if ($this->Deviceusers->save($deviceuser)) {

                $this->Flash->success(__('The {0} has been saved.', 'Deviceuser'));
                // debug($session->read('_referer'));
                if ($session->read('_referer') == '') {
                    return $this->redirect(['action' => 'index']);
                } else {
                    return $this->redirect($session->read('_referer'));
                }
            } else {
                $this->Flash->error(__('The {0} could not be saved. Please, try again.', 'Deviceuser'));
            }
        }

        if (($session->read('_user_type') != 1) && ($session->read('_user_type') != 2)) {
            $device = TableRegistry::get('Devices');
            $devices = $device->find('list')
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
                ->andwhere(['Deviceusers.deviceusers_startdate <= NOW()'])
                ->andwhere(['Deviceusers.deviceusers_enddate >= NOW()'])
                ->andwhere(['Deviceusers.users_id ' => $session->read('_user_id')])
                ->toArray();

            $users = TableRegistry::get('Users');
            $users  = $users->find('list')
                ->contain(['Usertypes', 'ParentUsers', 'Companies', 'States'])
                ->where(['Usertypes.usertype_level >' => $session->read('_user_type_level')]);
        } else {
            $devices = $this->Deviceusers->Devices->find('list', ['limit' => 200]);
            // $users = $this->Deviceusers->Users->find('list');
            $users = $this->Deviceusers->Users->find('list', [
                'limit' => 200,
                'valueField' => function ($row) {
                    return $row['user_shortname'];
                }
            ]);
        }

        //        $devices = $this->Deviceusers->Devices->find('list', ['limit' => 200]);
        //        $users = $this->Deviceusers->Users->find('list', ['limit' => 200]);
        $this->set(compact('deviceuser', 'devices', 'users', '_referer'));
        $this->set('_serialize', ['deviceuser']);
    }

    /**
     * Delete method
     *
     * @param string|null $id Deviceuser id.
     * @return \Cake\Network\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $deviceuser = $this->Deviceusers->get($id);
        if ($this->Deviceusers->delete($deviceuser)) {
            $this->Flash->success(__('The {0} has been deleted.', 'Deviceuser'));
        } else {
            $this->Flash->error(__('The {0} could not be deleted. Please, try again.', 'Deviceuser'));
        }
        return $this->redirect(['action' => 'index']);
    }

    public function delink($id = null)
    {
        $deviceuser = $this->Deviceusers->get($id);
        $deviceuser->deviceusers_status = 0;
        if ($this->Deviceusers->save($deviceuser)) {
            $this->Flash->success(__('The {0} has been delinked.', 'Deviceuser'));
        } else {
            $this->Flash->error(__('The {0} could not be delinked. Please, try again.', 'Deviceuser'));
        }
        return $this->redirect(['action' => 'index']);
    }
}
