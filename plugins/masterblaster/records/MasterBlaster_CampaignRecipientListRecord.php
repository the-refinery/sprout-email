<?php
namespace Craft;

class MasterBlaster_CampaignRecipientListRecord extends BaseRecord
{	
	/**
	 * Return table name corresponding to this record
	 * @return string
	 */
	public function getTableName()
	{
		return 'masterblaster_campaign_recipient_lists';
	}

	/**
	 * These have to be explicitly defined in order for the plugin to install
	 * @return array
	 */
    public function defineAttributes()
    {
        return array(
            'recipientListId' => array(AttributeType::Number),
        	'campaignId' 	  => array(AttributeType::Number),
            'dateCreated'     => array(AttributeType::DateTime),
            'dateUpdated'     => array(AttributeType::DateTime),
        );
    }

}
