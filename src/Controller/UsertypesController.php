<?php
namespace App\Controller;

use App\Controller\AppController;

/**
 * Usertypes Controller
 *
 * @property \App\Model\Table\UsertypesTable $Usertypes
 *
 * @method \App\Model\Entity\Usertype[]|\Cake\Datasource\ResultSetInterface paginate($object = null, array $settings = [])
 */
class UsertypesController extends AppController
{

    /**
     * Index method
     *
     * @return \Cake\Http\Response|void
     */
    public function index()
    {
        $usertypes = $this->paginate($this->Usertypes);

        $this->set(compact('usertypes'));
    }

    /**
     * View method
     *
     * @param string|null $id Usertype id.
     * @return \Cake\Http\Response|void
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $usertype = $this->Usertypes->get($id, [
            'contain' => []
        ]);

        $this->set('usertype', $usertype);
    }

    /**
     * Add method
     *
     * @return \Cake\Network\Response|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $usertype = $this->Usertypes->newEntity();
        if ($this->request->is('post')) {
            $usertype = $this->Usertypes->patchEntity($usertype, $this->request->data);
            if ($this->Usertypes->save($usertype)) {
                $this->Flash->success(__('The {0} has been saved.', 'Usertype'));
                return $this->redirect(['action' => 'index']);
            } else {
                $this->Flash->error(__('The {0} could not be saved. Please, try again.', 'Usertype'));
            }
        }
        $this->set(compact('usertype'));
        $this->set('_serialize', ['usertype']);
    }

    /**
     * Edit method
     *
     * @param string|null $id Usertype id.
     * @return \Cake\Network\Response|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Network\Exception\NotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $usertype = $this->Usertypes->get($id, [
            'contain' => []
        ]);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $usertype = $this->Usertypes->patchEntity($usertype, $this->request->data);
            if ($this->Usertypes->save($usertype)) {
                $this->Flash->success(__('The {0} has been saved.', 'Usertype'));
                return $this->redirect(['action' => 'index']);
            } else {
                $this->Flash->error(__('The {0} could not be saved. Please, try again.', 'Usertype'));
            }
        }
        $this->set(compact('usertype'));
        $this->set('_serialize', ['usertype']);
    }

    /**
     * Delete method
     *
     * @param string|null $id Usertype id.
     * @return \Cake\Network\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $usertype = $this->Usertypes->get($id);
        if ($this->Usertypes->delete($usertype)) {
            $this->Flash->success(__('The {0} has been deleted.', 'Usertype'));
        } else {
            $this->Flash->error(__('The {0} could not be deleted. Please, try again.', 'Usertype'));
        }
        return $this->redirect(['action' => 'index']);
    }
}
