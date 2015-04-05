<?php
namespace Craft;

class SproutEmail_DefaultMailerService extends BaseApplicationComponent
{
	/**
	 * @var SproutEmail_MailerSettingsModel
	 */
	protected $settings;

	/**
	 * @param int $id
	 *
	 * @return SproutEmail_DefaultMailerRecipientModel|null
	 */
	public function getRecipientById($id)
	{
		$record = SproutEmail_DefaultMailerRecipientRecord::model()->with('recipientLists')->findById((int) $id);

		if ($record)
		{
			return SproutEmail_DefaultMailerRecipientModel::populateModel($record);
		}
	}

	/**
	 * @param int $id
	 *
	 * @return SproutEmail_DefaultMailerRecipientListModel|null
	 */
	public function getRecipientListById($id)
	{
		if (($record = SproutEmail_DefaultMailerRecipientListRecord::model()->with('recipients')->findById((int) $id)))
		{
			return SproutEmail_DefaultMailerRecipientListModel::populateModel($record);
		}
	}

	/**
	 * @param int $handle
	 *
	 * @return SproutEmail_DefaultMailerRecipientListModel|null
	 */
	public function getRecipientListByHandle($handle)
	{
		if (($record = SproutEmail_DefaultMailerRecipientListRecord::model()->with('recipients')->findByAttributes(array('handle' => $handle))))
		{
			return SproutEmail_DefaultMailerRecipientListModel::populateModel($record);
		}
	}

	/**
	 * @return SproutEmail_DefaultMailerRecipientModel[]|null
	 */
	public function getRecipients()
	{
		$records = SproutEmail_DefaultMailerRecipientRecord::model()->findAll();

		if ($records)
		{
			return SproutEmail_DefaultMailerRecipientModel::populateModels($records);
		}
	}

	/**
	 * @param array $listIds
	 *
	 * @return SproutEmail_DefaultMailerRecipientModel[]|array
	 */
	public function getRecipientsByRecipientListIds(array $listIds = null)
	{
		$recipients = array();

		if ($listIds && count($listIds))
		{
			foreach ($listIds as $listId)
			{
				$list = $this->getRecipientListById($listId);

				if ($list && isset($list->recipients) && count($list->recipients))
				{
					foreach ($list->recipients as $recipient)
					{
						$recipients[$recipient->id] = SproutEmail_DefaultMailerRecipientModel::populateModel($recipient);
					}
				}
			}
		}

		return $recipients;
	}

	/**
	 * @return SproutEmail_DefaultMailerRecipientListModel[]|null
	 */
	public function getRecipientLists()
	{
		$records = SproutEmail_DefaultMailerRecipientListRecord::model()->with('recipients')->findAll();

		if ($records)
		{
			return SproutEmail_DefaultMailerRecipientListModel::populateModels($records);
		}
	}

	/**
	 * @param int $id
	 *
	 * @return SproutEmail_DefaultMailerRecipientListModel[]|null
	 */
	public function getRecipientListsByRecipientId($id)
	{
		$lists         = array();
		$record        = SproutEmail_DefaultMailerRecipientListRecipientRecord::model();
		$relationships = $record->findAllByAttributes(array('recipientId' => $id));

		if (count($relationships))
		{
			foreach ($relationships as $relationship)
			{
				$lists[] = sproutEmailDefaultMailer()->getRecipientListById($relationship->recipientListId);
			}
		}

		return $lists;
	}

	/**
	 * @param null|array $selected
	 *
	 * @return \Twig_Markup
	 */
	public function getRecipientListsHtml($selected = null)
	{
		$lists   = $this->getRecipientLists();
		$options = array();

		if (count($lists))
		{
			foreach ($lists as $list)
			{
				$options[] = array(
					'label' => sprintf('%s (%d)', $list->name, count($list->recipients)),
					'value' => $list->id
				);
			}
		}

		$html = craft()->templates->renderMacro(
			'_includes/forms', 'checkboxGroup', array(
				array(
					'id'      => 'recipientLists',
					'name'    => 'recipient[recipientLists]',
					'options' => $options,
					'values'  => $selected,
				)
			)
		);

		return TemplateHelper::getRaw($html);
	}

	public function saveRecipientList(SproutEmail_DefaultMailerRecipientListModel &$model)
	{
		if (isset($model->id) && is_numeric($model->id))
		{
			$record = SproutEmail_DefaultMailerRecipientListRecord::model()->populateRecord($model);
		}
		else
		{
			$record = new SproutEmail_DefaultMailerRecipientListRecord();

			$record->name   = $model->name;
			$record->handle = $model->handle;
		}

		if ($record->validate())
		{
			try
			{
				$record->save(false);

				return true;
			}
			catch (\Exception $e)
			{
				$model->addError('save', $e->getMessage());
			}
		}
		else
		{
			$model->addErrors($record->getErrors());
		}

		return false;
	}

	public function saveRecipient(SproutEmail_DefaultMailerRecipientModel &$model)
	{
		$isNewElement = !$model->id;

		if (!$isNewElement)
		{
			$record = SproutEmail_DefaultMailerRecipientRecord::model()->findById($model->id);

			if (!$record)
			{
				throw new Exception(Craft::t('No recipient exists with the id “{id}”', array('id' => $model->id)));
			}
		}
		else
		{
			$record = new SproutEmail_DefaultMailerRecipientRecord();
		}

		$record->setAttributes($model->getAttributes(), false);

		$record->validate();

		$model->addErrors($record->getErrors());

		if (!$model->hasErrors())
		{
			$transaction = craft()->db->getCurrentTransaction() === null ? craft()->db->beginTransaction() : null;

			try
			{
				if (craft()->elements->saveElement($model, true))
				{
					$saved = false;

					if ($isNewElement)
					{
						$record->id = $model->id;
					}

					if ($record->save(false))
					{
						$model->id = $record->id;

						$saved = $this->saveRecipientListRecipientRelations($record->id, $model->recipientLists);
					}

					if ($transaction !== null && $saved)
					{
						$transaction->commit();
					}

					return $saved;
				}
			}
			catch (\Exception $e)
			{
				if ($transaction !== null)
				{
					$transaction->rollback();
				}

				throw $e;
			}
		}

		return false;
	}

	public function saveRecipientListRecipientRelations($recipientId, array $recipientListIds = array())
	{
		try
		{
			SproutEmail_DefaultMailerRecipientListRecipientRecord::model()->deleteAll('recipientId = :recipientId', array(':recipientId' => $recipientId));
		}
		catch (Exception $e)
		{
			sproutEmail()->error($e->getMessage());
		}

		if (count($recipientListIds))
		{
			foreach ($recipientListIds as $listId)
			{
				$list = $this->getRecipientListById($listId);

				if ($list)
				{
					$relation = new SproutEmail_DefaultMailerRecipientListRecipientRecord();

					$relation->recipientId     = $recipientId;
					$relation->recipientListId = $list->id;

					if (!$relation->save(false))
					{
						throw new Exception(print_r($relation->getErrors(), true));
					}
				}
				else
				{
					throw new Exception('The recipient list with id '.$listId.' does not exists.');
				}
			}
		}

		return true;
	}

	/**
	 * @param SproutEmail_CampaignModel $campaign
	 * @param mixed                     $element
	 */
	public function sendNotification(SproutEmail_CampaignModel $campaign, $element = null)
	{
		if (count($campaign->entries))
		{
			$email = new EmailModel();

			foreach ($campaign->entries as $entry)
			{
				$listIds = array();

				if ($entry->recipientLists && count($entry->recipientLists))
				{
					foreach ($entry->recipientLists as $list)
					{
						$listIds[] = $list->list;
					}
				}

				$entry      = SproutEmail_EntryModel::populateModel($entry);
				$recipients = $this->getRecipientsByRecipientListIds($listIds);
				$recipients = array_merge($recipients, sproutEmail()->notifications->getDynamicRecipientsFromElement($element));

				if ($recipients && count($recipients))
				{
					$email->subject   = sproutEmail()->renderObjectTemplateSafely($entry->subjectLine, $element);
					$email->fromName  = sproutEmail()->renderObjectTemplateSafely($entry->fromName, $element);
					$email->fromEmail = sproutEmail()->renderObjectTemplateSafely($entry->fromEmail, $element);
					$email->replyTo   = sproutEmail()->renderObjectTemplateSafely($entry->replyTo, $element);

					if (strpos($email->replyTo, '{') === 0)
					{
						$email->replyTo = $email->fromEmail;
					}

					sproutEmail()->renderObjectContentSafely($entry, $element);

					$vars            = sproutEmail()->notifications->prepareNotificationTemplateVariables($entry, $element);
					$email->body     = sproutEmail()->renderSiteTemplateIfExists($campaign->template.'.txt', $vars);
					$email->htmlBody = sproutEmail()->renderSiteTemplateIfExists($campaign->template, $vars);

					if (!empty($email->body))
					{
						foreach ($recipients as $recipient)
						{
							$email->toEmail     = $recipient->email;
							$email->toFirstName = $recipient->firstName;
							$email->toLastName  = $recipient->lastName;

							try
							{
								craft()->email->sendEmail($email);
							}
							catch (\Exception $e)
							{
								sproutEmail()->error($e->getMessage());
							}
						}
					}
				}
			}
		}
	}

	public function exportEntry(SproutEmail_EntryModel $entry, SproutEmail_CampaignModel $campaign)
	{
		$params = array('entry' => $entry, 'campaign' => $campaign);
		$email  = array(
			'fromEmail' => $entry->fromEmail,
			'fromName'  => $entry->fromName,
			'subject'   => $entry->subjectLine,
		);

		if ($entry->replyTo && filter_var($entry->replyTo, FILTER_VALIDATE_EMAIL))
		{
			$email['replyTo'] = $entry->replyTo;
		}

		$recipients = array();

		if (($entryRecipientLists = SproutEmail_EntryRecipientListRecord::model()->findAllByAttributes(array('entryId' => $entry->id))))
		{
			foreach ($entryRecipientLists as $entryRecipientList)
			{
				if (($recipientList = $this->getRecipientListById($entryRecipientList->list)))
				{
					$recipients = array_merge($recipients, $recipientList->recipients);
				}
			}
		}

		$email = EmailModel::populateModel($email);

		foreach ($recipients as $recipient)
		{
			try
			{
				$params['recipient'] = $recipient;
				$email->body         = sproutEmail()->renderSiteTemplateIfExists($campaign->template.'.txt', $params);
				$email->htmlBody     = sproutEmail()->renderSiteTemplateIfExists($campaign->template, $params);

				$email->setAttribute('toEmail', $recipient->email);
				$email->setAttribute('toFirstName', $recipient->firstName);
				$email->setAttribute('toLastName', $recipient->lastName);

				craft()->email->sendEmail($email);
			}
			catch (\Exception $e)
			{
				throw $e;
			}
		}
	}


	/**
	 * Handles sproutCommerce.saveProduct events to create dynamic recipient lists
	 *
	 * @param Event $event
	 */
	public function handleSaveProduct(Event $event)
	{
		// New entries create a new dynamic recipient list
		if (isset($event->params['isNewProduct']) && $event->params['isNewProduct'])
		{
			$list = new SproutEmail_DefaultMailerRecipientListModel();

			$list->name   = $event->params['product']->title;
			$list->handle = $event->params['product']->slug;

			try
			{
				sproutEmailDefaultMailer()->saveRecipientList($list);
			}
			catch (\Exception $e)
			{
				sproutEmail()->error($e->getMessage());
			}
		}
	}

	/**
	 * Handles sproutCommerce.checkoutEnd events to add dynamic recipients to a lists
	 *
	 * @param Event $event
	 */
	public function handleCheckoutEnd(Event $event)
	{
		if (isset($event->params['checkout']->success) && $event->params['checkout']->success)
		{
			$order = $event->params['checkout']->order;

			if (count($order->products) && count($order->payments))
			{
				foreach ($order->products as $purchasedProduct)
				{
					$list = sproutEmailDefaultMailer()->getRecipientListByHandle($purchasedProduct->product->slug);

					if ($list)
					{
						foreach ($order->payments as $payment)
						{
							$recipient                 = new SproutEmail_DefaultMailerRecipientModel();
							$recipient->email          = $payment->email;
							$recipient->firstName      = $payment->firstName;
							$recipient->lastName       = $payment->lastName;
							$recipient->recipientLists = array($list->id);

							try
							{
								if (!sproutEmailDefaultMailer()->saveRecipient($recipient))
								{
									sproutEmail()->error($recipient->getErrors());
								}
							}
							catch (\Exception $e)
							{
								sproutEmail()->error($e->getMessage());
							}
						}
					}
				}
			}
		}
	}
}