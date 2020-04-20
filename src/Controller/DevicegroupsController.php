<?php
namespace App\Controller;

use App\Controller\AppController;

/**
 * Devicegroups Controller
 *
 * @property \App\Model\Table\DevicegroupsTable $Devicegroups
 *
 * @method \App\Model\Entity\Devicegroup[]|\Cake\Datasource\ResultSetInterface paginate($object = null, array $settings = [])
 */
class DevicegroupsController extends AppController
{

    /**
     * Index method
     *
     * @return \Cake\Http\Response|void
     */
    public function index()
    {
        $devicegroups = $this->paginate($this->Devicegroups);

        $this->set(compact('devicegroups'));
    }

    /**
     * View method
     *
     * @param string|null $id Devicegroup id.
     * @return \Cake\Http\Response|void
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $devicegroup = $this->Devicegroups->get($id, [
            'contain' => []
        ]);

        $this->set('devicegroup', $devicegroup);
    }

    /**
     * Add method
     *
     * @return \Cake\Network\Response|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $devicegroup = $this->Devicegroups->newEntity();
        if ($this->request->is('post')) {
            $devicegroup = $this->Devicegroups->patchEntity($devicegroup, $this->request->data);
            if ($this->Devicegroups->save($devicegroup)) {
                $this->Flash->success(__('The {0} has been saved.', 'Devicegroup'));
                return $this->redirect(['action' => 'index']);
            } else {
                $this->Flash->error(__('The {0} could not be saved. Please, try again.', 'Devicegroup'));
            }
        }
        $this->set(compact('devicegroup'));
        $this->set('_serialize', ['devicegroup']);
    }

    /**
     * Edit method
     *
     * @param string|null $id Devicegroup id.
     * @return \Cake\Network\Response|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Network\Exception\NotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $devicegroup = $this->Devicegroups->get($id, [
            'contain' => []
        ]);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $devicegroup = $this->Devicegroups->patchEntity($devicegroup, $this->request->data);
            if ($this->Devicegroups->save($devicegroup)) {
                $this->Flash->success(__('The {0} has been saved.', 'Devicegroup'));
                return $this->redirect(['action' => 'index']);
            } else {
                $this->Flash->error(__('The {0} could not be saved. Please, try again.', 'Devicegroup'));
            }
        }
        $this->set(compact('devicegroup'));
        $this->set('_serialize', ['devicegroup']);
    }

    /**
     * Delete method
     *
     * @param string|null $id Devicegroup id.
     * @return \Cake\Network\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $devicegroup = $this->Devicegroups->get($id);
        if ($this->Devicegroups->delete($devicegroup)) {
            $this->Flash->success(__('The {0} has been deleted.', 'Devicegroup'));
        } else {
            $this->Flash->error(__('The {0} could not be deleted. Please, try again.', 'Devicegroup'));
        }
        return $this->redirect(['action' => 'index']);
    }
}
