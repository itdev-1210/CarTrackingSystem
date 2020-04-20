<?php
namespace App\Controller;

use App\Controller\AppController;

/**
 * TcPositions Controller
 *
 * @property \App\Model\Table\TcPositionsTable $TcPositions
 *
 * @method \App\Model\Entity\TcPosition[]|\Cake\Datasource\ResultSetInterface paginate($object = null, array $settings = [])
 */
class TcPositionsController extends AppController
{

    /**
     * Index method
     *
     * @return \Cake\Http\Response|void
     */
    public function index()
    {
        $tcPositions = $this->paginate($this->TcPositions);

        $this->set(compact('tcPositions'));
    }

    /**
     * View method
     *
     * @param string|null $id Tc Position id.
     * @return \Cake\Http\Response|void
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $tcPosition = $this->TcPositions->get($id, [
            'contain' => []
        ]);

        $this->set('tcPosition', $tcPosition);
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $tcPosition = $this->TcPositions->newEntity();
        if ($this->request->is('post')) {
            $tcPosition = $this->TcPositions->patchEntity($tcPosition, $this->request->getData());
            if ($this->TcPositions->save($tcPosition)) {
                $this->Flash->success(__('The tc position has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The tc position could not be saved. Please, try again.'));
        }
        $this->set(compact('tcPosition'));
    }

    /**
     * Edit method
     *
     * @param string|null $id Tc Position id.
     * @return \Cake\Http\Response|null Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Network\Exception\NotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $tcPosition = $this->TcPositions->get($id, [
            'contain' => []
        ]);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $tcPosition = $this->TcPositions->patchEntity($tcPosition, $this->request->getData());
            if ($this->TcPositions->save($tcPosition)) {
                $this->Flash->success(__('The tc position has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The tc position could not be saved. Please, try again.'));
        }
        $this->set(compact('tcPosition'));
    }

    /**
     * Delete method
     *
     * @param string|null $id Tc Position id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $tcPosition = $this->TcPositions->get($id);
        if ($this->TcPositions->delete($tcPosition)) {
            $this->Flash->success(__('The tc position has been deleted.'));
        } else {
            $this->Flash->error(__('The tc position could not be deleted. Please, try again.'));
        }

        return $this->redirect(['action' => 'index']);
    }
}
