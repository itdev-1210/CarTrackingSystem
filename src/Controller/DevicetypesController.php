<?php
namespace App\Controller;

use App\Controller\AppController;

/**
 * Devicetypes Controller
 *
 * @property \App\Model\Table\DevicetypesTable $Devicetypes
 *
 * @method \App\Model\Entity\Devicetype[]|\Cake\Datasource\ResultSetInterface paginate($object = null, array $settings = [])
 */
class DevicetypesController extends AppController
{

    /**
     * Index method
     *
     * @return \Cake\Http\Response|void
     */
    public function index()
    {
        $devicetypes = $this->paginate($this->Devicetypes);

        $this->set(compact('devicetypes'));
    }

    /**
     * View method
     *
     * @param string|null $id Devicetype id.
     * @return \Cake\Http\Response|void
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $devicetype = $this->Devicetypes->get($id, [
            'contain' => []
        ]);

        $this->set('devicetype', $devicetype);
    }

    /**
     * Add method
     *
     * @return \Cake\Network\Response|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $devicetype = $this->Devicetypes->newEntity();
        if ($this->request->is('post')) {
            $devicetype = $this->Devicetypes->patchEntity($devicetype, $this->request->data);
            if ($this->Devicetypes->save($devicetype)) {
                $this->Flash->success(__('The {0} has been saved.', 'Devicetype'));
                return $this->redirect(['action' => 'index']);
            } else {
                $this->Flash->error(__('The {0} could not be saved. Please, try again.', 'Devicetype'));
            }
        }
        $this->set(compact('devicetype'));
        $this->set('_serialize', ['devicetype']);
    }

    /**
     * Edit method
     *
     * @param string|null $id Devicetype id.
     * @return \Cake\Network\Response|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Network\Exception\NotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $devicetype = $this->Devicetypes->get($id, [
            'contain' => []
        ]);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $devicetype = $this->Devicetypes->patchEntity($devicetype, $this->request->data);
            if ($this->Devicetypes->save($devicetype)) {
                $this->Flash->success(__('The {0} has been saved.', 'Devicetype'));
                return $this->redirect(['action' => 'index']);
            } else {
                $this->Flash->error(__('The {0} could not be saved. Please, try again.', 'Devicetype'));
            }
        }
        $this->set(compact('devicetype'));
        $this->set('_serialize', ['devicetype']);
    }

    /**
     * Delete method
     *
     * @param string|null $id Devicetype id.
     * @return \Cake\Network\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $devicetype = $this->Devicetypes->get($id);
        if ($this->Devicetypes->delete($devicetype)) {
            $this->Flash->success(__('The {0} has been deleted.', 'Devicetype'));
        } else {
            $this->Flash->error(__('The {0} could not be deleted. Please, try again.', 'Devicetype'));
        }
        return $this->redirect(['action' => 'index']);
    }
}
