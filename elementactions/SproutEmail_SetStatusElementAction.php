<?php
namespace Craft;

/**
 * Class SproutEmail_SetStatusElementAction
 *
 * @package Craft
 */
class SproutEmail_SetStatusElementAction extends BaseElementAction
{
	/**
	 * @return string
	 */
	public function getTriggerHtml()
	{
		return craft()->templates->render('sproutemail/_components/elementactions/setStatus');
	}

	/**
	 * @param ElementCriteriaModel $criteria
	 *
	 * @return bool
	 */
	public function performAction(ElementCriteriaModel $criteria)
	{
		$status   = $this->getParams()->status;
		$enable = 0;

		$elementIds = $criteria->ids();

		switch ($status)
		{
			case BaseElementModel::ENABLED:
			{
				$enable = 1;
				break;
			}
			case BaseElementModel::DISABLED:
			{
				$enable = 0;
				break;
			}
		}

		// Update their statuses
		craft()->db->createCommand()->update(
			'elements',
			array('enabled' => $enable),
			array('in', 'id', $elementIds)
		);

		if ($status == BaseElementModel::ENABLED)
		{
			// Enable their locale as well
			craft()->db->createCommand()->update(
				'elements_i18n',
				array('enabled' => $enable),
				array('and', array('in', 'elementId', $elementIds), 'locale = :locale'),
				array(':locale' => $criteria->locale)
			);
		}

		// Clear their template caches
		craft()->templateCache->deleteCachesByElementId($elementIds);

		// Trigger an 'onSetStatus' event
		$event = new Event($this, array(
			'criteria'   => $criteria,
			'elementIds' => $elementIds,
			'status'     => $status
		));

		sproutEmail()->onSetStatus($event);

		$this->setMessage(Craft::t('Statuses updated.'));

		return true;
	}

	/**
	 * @return array
	 */
	protected function defineParams()
	{
		return array(
			'status' => array(
				AttributeType::Enum,
				'values'   => array(
					BaseElementModel::DISABLED,
					BaseElementModel::ENABLED
				),
				'required' => true
			)
		);
	}
}
