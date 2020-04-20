<?php
namespace App\Controller;

use App\Controller\AppController;

/**
 * Documenttypes Controller
 *
 * @property \App\Model\Table\DocumenttypesTable $Documenttypes
 *
 * @method \App\Model\Entity\Documenttype[]|\Cake\Datasource\ResultSetInterface paginate($object = null, array $settings = [])
 */
class DocumenttypesController extends AppController
{

    /**
     * Index method
     *
     * @return \Cake\Http\Response|void
     */
    public function index()
    {
        $documenttypes = $this->paginate($this->Documenttypes);

        $this->set(compact('documenttypes'));
    }

    /**
     * View method
     *
     * @param string|null $id Documenttype id.
     * @return \Cake\Http\Response|void
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $documenttype = $this->Documenttypes->get($id, [
            'contain' => []
        ]);

        $this->set('documenttype', $documenttype);
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $documenttype = $this->Documenttypes->newEntity();
        if ($this->request->is('post')) {
            $documenttype = $this->Documenttypes->patchEntity($documenttype, $this->request->getData());
            if ($this->Documenttypes->save($documenttype)) {
                $this->Flash->success(__('The documenttype has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The documenttype could not be saved. Please, try again.'));
        }
        $this->set(compact('documenttype'));
    }

    /**
     * Edit method
     *
     * @param string|null $id Documenttype id.
     * @return \Cake\Http\Response|null Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Network\Exception\NotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $documenttype = $this->Documenttypes->get($id, [
            'contain' => []
        ]);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $documenttype = $this->Documenttypes->patchEntity($documenttype, $this->request->getData());
            if ($this->Documenttypes->save($documenttype)) {
                $this->Flash->success(__('The documenttype has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The documenttype could not be saved. Please, try again.'));
        }
        $this->set(compact('documenttype'));
    }

    /**
     * Delete method
     *
     * @param string|null $id Documenttype id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $documenttype = $this->Documenttypes->get($id);
        if ($this->Documenttypes->delete($documenttype)) {
            $this->Flash->success(__('The documenttype has been deleted.'));
        } else {
            $this->Flash->error(__('The documenttype could not be deleted. Please, try again.'));
        }

        return $this->redirect(['action' => 'index']);
    }
}
