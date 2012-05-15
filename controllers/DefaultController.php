<?php

class DefaultController extends BaseEventTypeController {
	public function actionCreate() {
		if (!$patient = Patient::model()->findByPk($_REQUEST['patient_id'])) {
			throw new CHttpException(403, 'Invalid patient_id.');
		}
		$this->showAllergyWarning($patient);
		parent::actionCreate();
	}

	public function actionUpdate($id) {
		if (!$event = Event::model()->findByPk($id)) {
			throw new CHttpException(403, 'Invalid event id.');
		}
		$this->showAllergyWarning($event->episode->patient);
		parent::actionUpdate($id);
	}

	public function actionView($id) {
		if (!$event = Event::model()->findByPk($id)) {
			throw new CHttpException(403, 'Invalid event id.');
		}
		
		// Clear any stale warning
		Yii::app()->user->getFlash('warning.prescription_allergy');
		
		// Get prescription details element
		$element = Element_OphDrPrescription_Details::model()->findByAttributes(array('event_id' => $event->id));
		$patient = $event->episode->patient;
		foreach($element->items as $item) {
			if($patient->hasAllergy($item->drug_id)) {
				$this->showAllergyWarning($event->episode->patient);
				break;
			}
		}
		parent::actionView($id);
	}

	protected function showAllergyWarning($patient) {
		if($patient->allergies) {
			$allergy_array = array();
			foreach($patient->allergies as $allergy) {
				$allergy_array[] = $allergy->name;
			}
			Yii::log('setting flash');
			Yii::app()->user->setFlash('warning.prescription_allergy', 'Warning: Patient is allergic to '.implode(', ',$allergy_array));
		}
	}
	
	public function updateElements($elements, $data, $event) {
		// TODO: Move model aftersave stuff in here
		return parent::updateElements($elements, $data, $event);
	}

	public function actionDrugList() {
		if(Yii::app()->request->isAjaxRequest) {
			$criteria = new CDbCriteria();
			if(isset($_GET['term']) && $term = $_GET['term']) {
				$criteria->addCondition('LOWER(name) LIKE :term');
				$params[':term'] = '%' . strtolower(strtr($term, array('%' => '\%'))) . '%';
			}
			if(isset($_GET['type_id']) && $type_id = $_GET['type_id']) {
				$criteria->addCondition('type_id = :type_id');
				$params[':type_id'] = $type_id;
			}
			if(isset($_GET['preservative_free']) && $preservative_free = $_GET['preservative_free']) {
				$criteria->addCondition('preservative_free = 1');
			}
			$criteria->order = 'name';
			$criteria->params = $params;
			$drugs = Drug::model()->findAll($criteria);
			$return = array();
			foreach($drugs as $drug) {
				$return[] = array(
						'label' => $drug->label,
						'value' => $drug->name,
						'id' => $drug->id,
				);
			}
			echo CJSON::encode($return);
		}
	}

	public function actionSetForm($key, $patient_id, $set_id) {
		$patient = Patient::model()->findByPk($patient_id);
		$drug_set_items = DrugSetItem::model()->findAllByAttributes(array('drug_set_id' => $set_id));
		foreach($drug_set_items as $drug_set_item) {
			$item = new OphDrPrescription_Item();
			$item->drug_id = $drug_set_item->drug_id;
			$item->loadDefaults();
			$this->renderPartial('form_Element_OphDrPrescription_Details_Item', array('key' => $key, 'item' => $item, 'patient' => $patient));
			$key++;
		}
	}

	public function actionItemForm($key, $patient_id, $drug_id) {
		$patient = Patient::model()->findByPk($patient_id);
		$item = new OphDrPrescription_Item();
		$item->drug_id = $drug_id;
		$item->loadDefaults();
		$this->renderPartial('form_Element_OphDrPrescription_Details_Item', array('key' => $key, 'item' => $item, 'patient' => $patient));
	}

	public function actionRouteOptions($key, $route_id) {
		$options = DrugRouteOption::model()->findAllByAttributes(array('drug_route_id' => $route_id));
		if($options) {
			echo CHtml::dropDownList('prescription_item['.$key.'][route_option_id]', null, CHtml::listData($options, 'id', 'name'), array('empty' => '-- Select --'));
		} else {
			echo '-';
		}
	}

}
