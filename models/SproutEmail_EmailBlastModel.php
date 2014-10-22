<?php
namespace Craft;

class SproutEmail_EmailBlastModel extends BaseElementModel
{
	protected $elementType = 'SproutEmail_EmailBlast';
	
	private $_fields;

	/**
	 * @access protected
	 * @return array
	 */
	protected function defineAttributes()
	{
		return array_merge(parent::defineAttributes(), array(
			'id'         => AttributeType::Number,
			'campaignId' => AttributeType::Number
		));
	}

	/*
	 * Returns the field layout used by this element.
	 *
	 * @return FieldLayoutModel|null
	 */
	public function getFieldLayout()
	{
		$campaignArray = craft()->sproutEmail->getSectionCampaigns($this->id);

		$campaignModel = new SproutEmail_CampaignModel();
		$campaignModel->setAttributes($campaignArray);

		return $campaignModel->getFieldLayout();
	}

	/**
	 * Returns the fields associated with this form.
	 *
	 * @return array
	 */
	public function getFields()
	{
		if (!isset($this->_fields))
		{
			$this->_fields = array();

			$fieldLayoutFields = $this->getFieldLayout()->getFields();

			foreach ($fieldLayoutFields as $fieldLayoutField)
			{
				$field = $fieldLayoutField->getField();
				$field->required = $fieldLayoutField->required;
				$this->_fields[] = $field;
			}
		}

		return $this->_fields;
	}

	/*
	 * Sets the fields associated with this form.
	 *
	 * @param array $fields
	 */
	public function setFields($fields)
	{
		$this->_fields = $fields;
	}

	/**
	 * Returns the element's CP edit URL.
	 *
	 * @return string|false
	 */
	public function getCpEditUrl()
	{
		$url = UrlHelper::getCpUrl('sproutemail/emailblasts/edit/'. $this->id);

		return $url;
	}
}