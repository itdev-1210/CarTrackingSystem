<?php
namespace App\Controller;

use App\Controller\AppController;
use Cake\ORM\TableRegistry;
use Cake\Core\Configure;
use Cake\Event\Event;
use Cake\Auth\DefaultPasswordHasher;

/**
 * Users Controller
 *
 * @property \App\Model\Table\UsersTable $Users
 *
 * @method \App\Model\Entity\User[]|\Cake\Datasource\ResultSetInterface paginate($object = null, array $settings = [])
 */
class UsersController extends AppController
{

    public $components = ['Cookie'];

    public function beforeFilter(Event $event) {
        parent::beforeFilter($event);
        $this->Auth->allow('resetpassword');
        $this->Auth->allow('ajaxvalidateuser');
    }

    /**
     * Index method
     *
     * @return \Cake\Http\Response|void
     */
    public function index()
    {
        $session = $this->request->session();
       // $this->paginate = [
       //     'contain' => ['Usertypes', 'ParentUsers', 'Companies', 'States']
       // ];
//        $users = $this->paginate($this->Users);
        if ($this->request->is(['post', 'put'])) {
                // echo "1";
                if (($session->read('_user_type') != 1) && ($session->read('_user_type') != 5)){
                    $result_set = $this->Users->find('all')
                                    ->where(['Users.user_shortname LIKE ' => '%'.$this->request->data('searchtext').'%'])
                                    ->orwhere(['Users.user_name LIKE ' => '%'.$this->request->data('searchtext').'%']);
                }else{
                    $result_set = $this->Users->find('all')
                                    ->where(['Users.user_shortname LIKE ' => '%'.$this->request->data('searchtext').'%'])
                                    ->orwhere(['Users.user_name LIKE ' => '%'.$this->request->data('searchtext').'%']);
                }

            $users = $this->paginate($result_set);

        }else{
            if (($session->read('_user_type') == 1)){
                $query = $this->Users->find('all')
                            ->contain(['Usertypes'])
                            ->where(['Users.user_status' => 1]);
                            // ->where(['Usertypes.usertype_level >' => $session->read('_user_type_level')]);
                $this->set('users', $this->paginate($query));
            }else{
                $query = $this->Users->find('all')
                            ->join([
                                'cusertypes' => [
                                    'table' => 'usertypes',
                                    'type' => 'LEFT',
                                    'conditions' => 'Users.usertypes_id = cusertypes.id'
                                ]
                            ])
                            ->where(['Users.user_status' => 1])
                            ->andwhere(['cusertypes.usertype_level > ' => $session->read('_user_type_level')]);
                $this->set('users', $this->paginate($query));
            }
        }

        $this->set(compact('users'));
    }


    public function treeview(){

    }

    /**
     * View method
     *
     * @param string|null $id User id.
     * @return \Cake\Http\Response|void
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $user = $this->Users->get($id, [
            'contain' => ['Usertypes', 'Users', 'Companies', 'States']
        ]);

        $this->set('user', $user);
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $session = $this->request->session();
        $user = $this->Users->newEntity();
        if ($this->request->is('post')) {

            $user = $this->Users->patchEntity($user, $this->request->getData());
            $_result = $this->Users->save($user);

            if ($_result) {
                $_user = $this->Users->get($_result->id, [
                    'contain' => []
                ]);
                $uploadPath = 'uploads/files/';

                // upload user's identification document
                if(!empty($this->request->data['user_identification']['name'])){
                    $fileName = $_result->id.'-'.date("Y-m-d H:i:s").'-'.$this->request->data['user_identification']['name'];
                    $uploadFile = $uploadPath.$fileName;

                    if(move_uploaded_file($this->request->data['user_identification']['tmp_name'], $uploadFile)){
                        $_user->user_identification = $uploadFile;
                    }else{
                        $this->Flash->error(__('Filed to upload identification. Please, try again.'));
                    }
                }
                // upload user's picture
                if(!empty($this->request->data['user_picture']['name'])){
                    $fileName = $_result->id.'-'.date("Y-m-d H:i:s").'-'.$this->request->data['user_picture']['name'];
                    $uploadFile = $uploadPath.$fileName;

                    if(move_uploaded_file($this->request->data['user_picture']['tmp_name'], $uploadFile)){
                        $_user->user_picture = $uploadFile;
                    }else{
                        $this->Flash->error(__('Filed to upload picture. Please, try again.'));
                    }
                }

                // generate user member id

                // get number of users added today

                $_fuser = $this->Users->find()
                            ->where(['created LIKE "'.date('Y-m-d').'%"'])
                            ->count();

                $_fuser = $_fuser;
                $_fuser = sprintf("%02d", ($_fuser));

                $_user_code = $this->request->data['user_location'].$_fuser.strtoupper(substr($this->request->data['user_fname'], 0, 1)).date('Ymd');

                if ($this->request->data['usertypes_id'] == 1){
                    $_user_code .= 'A';
                }else if ($this->request->data['usertypes_id'] == 2){
                    $_user_code .= 'M';
                }else if ($this->request->data['usertypes_id'] == 3){
                    $_user_code .= 'U';
                }

                $_user->user_shortname = $_user_code;

                if ($this->Users->save($_user)) {}

                $this->Flash->success(__('The user has been saved.'));
                return $this->redirect(['action' => 'index']);
            }else{
                $this->Flash->error(__('The user could not be saved. Please, try again.'));
            }
        }
        $usertypes = $this->Users->Usertypes->find('list', ['limit' => 200, 'conditions' => ['usertype_level >= ' => $session->read('_user_type_level')]]);
        $parentUsers = $this->Users->ParentUsers->find('list', ['limit' => 200]);
        $companies = $this->Users->Companies->find('list', ['limit' => 200]);
        $states = $this->Users->States->find('list', ['limit' => 200, 'conditions' => ['countries_id' => '101']]);
        $countries = $this->Users->Countries->find('list', ['limit' => 200, 'conditions' => ['id' => '101']]);
        $this->set(compact('user', 'usertypes', 'parentUsers', 'companies', 'states', 'countries'));
    }

    /**
     * Edit method
     *
     * @param string|null $id User id.
     * @return \Cake\Http\Response|null Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Network\Exception\NotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $session = $this->request->session();
        $user = $this->Users->get($id, [
            'contain' => []
        ]);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $uploadPath = 'uploads/files/';
            if(!empty($this->request->data['user_identification']['name'])){
                $fileName = $id.'-'.date("Y-m-d H:i:s").'-'.$this->request->data['user_identification']['name'];
                $uploadFile = $uploadPath.$fileName;

                if(move_uploaded_file($this->request->data['user_identification']['tmp_name'], $uploadFile)){
                    $this->request->data['user_identification'] = $uploadFile;
                }else{
                    $this->Flash->error(__('Filed to upload identification. Please, try again.'));
                }
            }else{
                $this->request->data['user_identification'] = $this->request->data['_user_identification'];
            }
            // upload user's picture
            if(!empty($this->request->data['user_picture']['name'])){
                $fileName = $id.'-'.date("Y-m-d H:i:s").'-'.$this->request->data['user_picture']['name'];
                $uploadFile = $uploadPath.$fileName;

                if(move_uploaded_file($this->request->data['user_picture']['tmp_name'], $uploadFile)){
                    $this->request->data['user_picture'] = $uploadFile;
                }else{
                    $this->Flash->error(__('Filed to upload picture. Please, try again.'));
                }
            }else{
                $this->request->data['user_picture'] = $this->request->data['_user_picture'];
            }
            $user = $this->Users->patchEntity($user, $this->request->getData());
            if ($this->Users->save($user)) {
                $this->Flash->success(__('The user has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The user could not be saved. Please, try again.'));
        }
        $usertypes = $this->Users->Usertypes->find('list', ['limit' => 200, 'conditions' => ['usertype_level >= ' => $session->read('_user_type_level')]]);
        $parentUsers = $this->Users->ParentUsers->find('list', ['limit' => 200]);
        $companies = $this->Users->Companies->find('list', ['limit' => 200]);
        $states = $this->Users->States->find('list', ['limit' => 200, 'conditions' => ['countries_id' => '101']]);
        $countries = $this->Users->Countries->find('list', ['limit' => 200, 'conditions' => ['id' => '101']]);
        $this->set(compact('user', 'usertypes', 'parentUsers', 'companies', 'states', 'countries'));
    }

    /**
     * Delete method
     *
     * @param string|null $id User id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $user = $this->Users->get($id);
        if ($this->Users->delete($user)) {
            $this->Flash->success(__('The user has been deleted.'));
        } else {
            $this->Flash->error(__('The user could not be deleted. Please, try again.'));
        }

        return $this->redirect(['action' => 'index']);
    }

    public function login(){
        // $this->set('title', 'Login');
        // $this->viewBuilder()->layout('login');

        if ($this->request->is('post')){
            // debug($this->request->data);

            $user = $this->Auth->identify();
            if ($user){
                $this->Auth->setUser($user);

                if ($user['user_status'] != 1){
                    $this->Flash->error('You account is not active. Please contact the administratot.');
                    return $this->redirect($this->Auth->logout());
                }else{
                    if (isset($this->request->data['remember_me'])){
                        $this->_setCookie($this->request->data['remember_me'], $this->request->data['user_name'], $this->request->data['user_password']);
                    }else{
                        $this->Cookie->delete('RememberMe');
                    }
                    
                    $session = $this->request->session();

                    $usertypesTable = TableRegistry::get('Usertypes');
                    $usertypes = $usertypesTable->get($user['usertypes_id'])->toArray();

                    $session->write('_user_id', $user['id']);
                    $session->write('_user_company_id', $user['companies_id']);
                    $session->write('_user_type', $user['usertypes_id']);
                    $session->write('_user_type_level', $usertypes['usertype_level']);
                    $session->write('_user_name', $user['user_name']);
                    $session->write('_user_shortname', $user['user_shortname']);
                    $session->write('_user_fname', $user['user_fname']);
                    $session->write('_user_lname', $user['user_lname']);
                    $session->write('_user_email', $user['user_email']);

                    return $this->redirect(['controller' => 'dashboards']);
                }

//                if ($user['usertypes_id'] == 4){
//                    $outlets = $this->Users->Outlets->find('all')
//                                ->where(['Outlets.id ' => $user['outlets_id']])->toArray();
//                    if ($outlets[0]->outlet_status == 0){
//                        $this->Flash->error('Awaiting verification');
//                        return $this->redirect(['action' => 'logout']);
//                    }else if ($outlets[0]->outlet_status == 2){
//                        $this->Flash->error('Account is not active please pay the registration fee and try again.');
//                        return $this->redirect(['action' => 'logout']);
//                    }else if ($outlets[0]->outlet_status == -1){
//                        $this->Flash->error('Account inactive. Please contact administrator.');
//                        return $this->redirect(['action' => 'logout']);
//                    }
//                }

                // if ($this->request->data('remember_me') !== null){

                // }
            }
            $this->Flash->error('Incorrect login information');
        }
        // debug($this->Cookie->read('RememberMe'));
        if (!empty($this->Cookie->read('RememberMe'))){
            $rememberme = $this->Cookie->read('RememberMe');
            $this->set(compact('rememberme'));
        }
    }

    protected function _setCookie($rememberme, $username, $password) {

		if (!$rememberme) {
			return false;
		}
		$data = [
			'username' => $username,
			'password' => $password
		];
		$this->Cookie->write('RememberMe', $data, true, '+1 week');

		return true;
	}

    public function logout(){
        $session = $this->request->session();
        $session->destroy();
        $this->Flash->error('You have been succefully logged out');
        return $this->redirect($this->Auth->logout());
    }

    public function ajaxvalidateuser(){

        $id = '';
        $rec_count = 1;

        $_response = array(
                'valid' => false,
                'message' => 'Invalid user'
        );

        if (isset($this->request->params['pass'][0])){
            if ($this->request->params['pass'][0] == 'user_name'){
                $id = (isset($this->request->params['pass'][1])?$this->request->params['pass'][1]:0);

                if (strlen($this->request->data('user_name')) >= 5){
                        if ($id != 0){
                                $query = $this->Users->find('all')
                                ->where(['Users.user_name LIKE ' => ''.$this->request->data('user_name').''])
                                ->andWhere(['Users.id !=' => $id]);
                                $rec_count = $query->count();
                        }else{
                                $query = $this->Users->find('all')
                                ->where(['Users.user_name = ' => $this->request->data('user_name')]);
                                $rec_count = $query->count();
                        }

                        if ($rec_count == 0){
                                $_response = array('valid' => true);
                        }else{
                                $_response = array(
                                                                'valid' => false,
                                                                'message' => 'User name already exists'
                                                        );
                        }
                }else{
                        $_response = array(
                                        'valid' => false,
                                        'message' => 'Country name must be more than 5 charecter.'
                        );
                }

            }else if ($this->request->params['pass'][0] == 'user_email'){
                $id = (isset($this->request->params['pass'][1])?$this->request->params['pass'][1]:0);

                if (($id != 0) && ($this->request->data('user_email') != '')){
                        $query = $this->Users->find('all')
                        ->where(['Users.user_email LIKE ' => ''.$this->request->data('user_email').''])
                        ->andWhere(['Users.id !=' => $id]);
                        $rec_count = $query->count();
                }else if ($this->request->data('user_email') != ''){
                        $query = $this->Users->find('all')
                        ->where(['Users.user_email LIKE ' => ''.$this->request->data('user_email').'']);
                        $rec_count = $query->count();
                }

                if ($rec_count == 0){
                        $_response = array('valid' => true);
                }else{
                        $_response = array(
                                                        'valid' => false,
                                                        'message' => 'User email already exists'
                                                );
                }
            }else if ($this->request->params['pass'][0] == 'user_old_password'){
                $id = (isset($this->request->params['pass'][1])?$this->request->params['pass'][1]:0);
                if (($id != 0) && ($this->request->data('user_old_password') != '')){
                    $query = $this->Users->find('all')
                    ->Where(['Users.id ' => $id])->toArray();
                }

                if ((new DefaultPasswordHasher)->check($this->request->data('user_old_password'), $query[0]->user_password)){
                    $_response = array('valid' => true);
                }else{
                    $_response = array(
                                'valid' => false,
                                'message' => 'Invalid password'
                            );
                }
            }
        }else{
            if ($this->request->data['type'] == 'valuser'){
                $query = $this->Users->find('all')
                    ->where(['Users.user_email LIKE ' => ''.$this->request->data('email').''])
                    ->andWhere(['Users.user_cellphone LIKE ' => '%'.$this->request->data('mobile').''])
                    ->toArray();

                if ((isset($query[0]['id'])) && ($query[0]['id'] >= 1)){
                    $_response = array('valid' => true, 'id' => $query[0]['id']);
                }
            }
        }

    	echo json_encode($_response);
    	die();
    }

    public function resetpassword(){
        // $this->viewBuilder()->layout('login');
        $session = $this->request->session();

        if ($this->request->is('post')){

            // debug($this->request->data);

            // $user = $this->Users->get('*', [
            //     'contain' => [],
            //     'conditions' => ['Users.user_name' => $this->request->data['user_email'], 'Users.user_cellphone' => $this->request->data['user_mobile']]
            // ]);

            // $user = $this->Users->get($this->request->data['id']);

            // $user = $this->Users->find('all')
            //         ->where(['Users.user_name' => $this->request->data['user_email']])
            //         ->andWhere(['Users.user_cellphone' => $this->request->data['user_mobile']]);
            //
            // $_query = $this->Users->Query();

            $query = $this->Users->query();
            $result = $query->update()
                        ->set(['user_password' =>  (new DefaultPasswordHasher)->hash($this->request->data['user_password'])])
                        ->where(['user_name' => $this->request->data['user_email']])
                        ->andWhere(['user_cellphone' => $this->request->data['user_mobile']])
                        ->execute();

            if ($result){
                $this->Flash->success(__('Password updated succesfully.', 'User'));
                return $this->redirect(['controller' => 'Dashboards']);
            }else{
                $this->Flash->error(__('Invalid email or phone. Please, try again.', 'User'));
            }

            // if (count($user->toArray()) > 0){
            //     debug($user);
            //
            //     $user->user_password = $this->request->data['user_password'];
            //
            //     if ($this->Users->save($user)) {
            //         return $this->redirect(['action' => 'logout']);
            //     } else {
            //         $this->Flash->error(__('The {0} could not be saved. Please, try again.', 'User'));
            //     }
            // }else{
            //     $this->Flash->error(__('Invalid email or phone. Please, try again.', 'User'));
            // }
        }
    }

    public function changepassword($id){
        $session = $this->request->session();
        $user = $this->Users->get($id, [
            'contain' => []
        ]);
        if ($this->request->is(['patch', 'post', 'put'])) {
            // $user->user_password = (new DefaultPasswordHasher)->hash($this->request->data['user_password']);
            $user->user_password = $this->request->data['user_password'];

            if ($this->Users->save($user)) {
                $this->Flash->success(__('Password has been updated succesfully.', 'User'));
                return $this->redirect(['controller' => 'Dashboards']);
            } else {
                $this->Flash->error(__('The {0} could not be saved. Please, try again.', 'User'));
            }
        }
        $this->set(compact('user'));
    }
}
