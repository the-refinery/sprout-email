<?php

namespace Craft;

/**
 * Class SproutEmail_NotificationEmailElementType
 */
class SproutEmail_NotificationEmailElementType extends BaseElementType
{
	/**
	 * @return string
	 */
	public function getName()
	{
		return Craft::t('Sprout Email Notification Emails');
	}

	/**
	 * @return bool
	 */
	public function hasTitles()
	{
		return true;
	}

	/**
	 * @return bool
	 */
	public function hasContent()
	{
		return true;
	}

	/**
	 * @inheritDoc IElementType::hasStatuses()
	 *
	 * @return bool
	 */
	public function hasStatuses()
	{
		return true;
	}

	/**
	 * @return array
	 */
	public function getStatuses()
	{
		return array(
			SproutEmail_NotificationEmailModel::ENABLED  => Craft::t('Enabled'),
			SproutEmail_NotificationEmailModel::PENDING  => Craft::t('Pending'),
			SproutEmail_NotificationEmailModel::DISABLED => Craft::t('Disabled')
		);
	}

	/**
	 * Returns this element type's sources.
	 *
	 * @param string|null $context
	 *
	 * @return array|false
	 */
	public function getSources($context = null)
	{
		$sources = array(
			'*' => array(
				'label' => Craft::t('All Notifications'),
			),
		);

		return $sources;
	}

	/**
	 * Returns the attributes that can be selected as table columns
	 *
	 * @return array
	 */
	public function defineAvailableTableAttributes()
	{
		$attributes = array(
			'title'       => array('label' => Craft::t('Subject Line')),
			'name'        => array('label' => Craft::t('Notification Name')),
			'dateCreated' => array('label' => Craft::t('Date Created')),
			'dateUpdated' => array('label' => Craft::t('Date Updated')),
			'send'        => array('label' => Craft::t('Send')),
			'preview'     => array('label' => Craft::t('Preview'), 'icon' => 'view')
		);

		return $attributes;
	}

	/**
	 * @param null $source
	 *
	 * @return array
	 */
	public function getDefaultTableAttributes($source = null)
	{
		$attributes = array();

		$attributes[] = 'title';
		$attributes[] = 'name';
		$attributes[] = 'dateCreated';
		$attributes[] = 'dateUpdated';
		$attributes[] = 'send';
		$attributes[] = 'preview';

		return $attributes;
	}

	/**
	 * @return mixed
	 */
	public function defineSortableAttributes()
	{
		$attributes['title']       = Craft::t('Subject Line');
		$attributes['name']        = Craft::t('Notification Name');
		$attributes['dateCreated'] = Craft::t('Date Created');
		$attributes['dateUpdated'] = Craft::t('Date Updated');

		return $attributes;
	}

	/**
	 * @inheritDoc IElementType::getTableAttributeHtml()
	 *
	 * @param BaseElementModel $element
	 * @param string           $attribute
	 *
	 * @return string
	 */
	public function getTableAttributeHtml(BaseElementModel $element, $attribute)
	{
		if ($attribute === 'send')
		{
			return craft()->templates->render('sproutemail/_partials/notifications/prepareLink', array(
				'notification' => $element
			));
		}

		if ($attribute === 'preview')
		{
			$shareUrl = null;

			if ($element->id && $element->getUrl())
			{
				$shareUrl = UrlHelper::getActionUrl('sproutEmail/notificationEmails/shareNotificationEmail', array(
					'notificationId' => $element->id,
				));
			}

			return craft()->templates->render('sproutemail/_partials/notifications/previewLinks', array(
				'email'    => $element,
				'shareUrl' => $shareUrl,
				'type'     => $attribute
			));
		}

		return parent::getTableAttributeHtml($element, $attribute);
	}

	/**
	 * @param ElementCriteriaModel $criteria
	 * @param array                $disabledElementIds
	 * @param array                $viewState
	 * @param null|string          $sourceKey
	 * @param null|string          $context
	 * @param bool                 $includeContainer
	 * @param bool                 $showCheckboxes
	 *
	 * @return string
	 */
	public function getIndexHtml($criteria, $disabledElementIds, $viewState, $sourceKey, $context, $includeContainer, $showCheckboxes)
	{
		craft()->templates->includeJsResource('sproutemail/js/sproutmodal.js');
		craft()->templates->includeJs('var sproutModalInstance = new SproutModal(); sproutModalInstance.init();');

		sproutEmail()->mailers->includeMailerModalResources();

		craft()->templates->includeCssResource('sproutemail/css/sproutemail.css');

		return parent::getIndexHtml($criteria, $disabledElementIds, $viewState, $sourceKey, $context, $includeContainer, $showCheckboxes);
	}

	/**
	 * Defines any custom element criteria attributes for this element type.
	 *
	 * @return array
	 */
	public function defineCriteriaAttributes()
	{
		return array(
			'title' => AttributeType::String
		);
	}

	/**
	 * Defines which model attributes should be searchable.
	 *
	 * @return array
	 */
	public function defineSearchableAttributes()
	{
		return array('title');
	}

	/**
	 * Modifies an element query targeting elements of this type.
	 *
	 * @param DbCommand            $query
	 * @param ElementCriteriaModel $criteria
	 *
	 * @return mixed
	 */
	public function modifyElementsQuery(DbCommand $query, ElementCriteriaModel $criteria)
	{
		// join with the table
		$query->addSelect('notificationemail.*')
			->join('sproutemail_notificationemails notificationemail', 'notificationemail.id = elements.id');

		if ($criteria->order)
		{
			// Trying to order by date creates ambiguity errors
			// Let's make sure mysql knows what we want to sort by
			if (stripos($criteria->order, 'elements.') === false)
			{
				$criteria->order = str_replace('dateCreated', 'notificationemail.dateCreated', $criteria->order);
				$criteria->order = str_replace('dateUpdated', 'notificationemail.dateUpdated', $criteria->order);
			}
		}
	}

	/**
	 * Gives us the ability to render campaign previews by using the Craft API and templates/render
	 *
	 * @param BaseElementModel $element
	 *
	 * @return array|bool
	 * @throws HttpException
	 */
	public function routeRequestForMatchedElement(BaseElementModel $element)
	{
		// Only expose notification emails that have tokens and allow Live Preview requests
		if (!craft()->request->getQuery(craft()->config->get('tokenParam')) && !craft()->request->isLivePreview())
		{
			throw new HttpException(404);
		}

		$extension = null;

		if (($type = craft()->request->getQuery('type')))
		{
			$extension = in_array(strtolower($type), array('txt', 'text')) ? '.txt' : null;
		}

		if (!craft()->templates->doesTemplateExist($element->template . $extension))
		{
			$templateName = $element->template . $extension;

			sproutEmail()->error(Craft::t("The template '{templateName}' could not be found", array(
				'templateName' => $templateName
			)));
		}

		$event = sproutEmail()->notificationEmails->getEventById($element->eventId);

		$object = $event ? $event->getMockedParams() : null;

		return array(
			'action' => 'templates/render',
			'params' => array(
				'template'  => $element->template . $extension,
				'variables' => array(
					'email'  => $element,
					'object' => $object
				)
			)
		);
	}

	/**
	 * @inheritDoc IElementType::getAvailableActions()
	 *
	 * @param string|null $source
	 *
	 * @return array|null
	 */
	public function getAvailableActions($source = null)
	{
		$deleteAction = craft()->elements->getAction('SproutEmail_NotificationEmailDelete');

		$deleteAction->setParams(array(
			'confirmationMessage' => Craft::t('Are you sure you want to delete the selected emails?'),
			'successMessage'      => Craft::t('Emails deleted.'),
		));

		$setStatusAction              = craft()->elements->getAction('SetStatus');
		$setStatusAction->onSetStatus = function (Event $event)
		{
			if ($event->params['status'] == BaseElementModel::ENABLED)
			{
				// Set a Date Updated as well
				craft()->db->createCommand()->update(
					'sproutemail_notificationemails',
					array('dateUpdated' => DateTimeHelper::currentTimeForDb()),
					array('and', array('in', 'id', $event->params['elementIds']))
				);
			}
		};

		return array($deleteAction, $setStatusAction);
	}

	/**
	 * Populates an element model based on a query result.
	 *
	 * @param array $row
	 *
	 * @return BaseModel
	 */
	public function populateElementModel($row)
	{
		return SproutEmail_NotificationEmailModel::populateModel($row);
	}

	/**
	 * @inheritDoc IElementType::getElementQueryStatusCondition()
	 *
	 * @param DbCommand $query
	 * @param string    $status
	 *
	 * @return array|false|string|void
	 */
	public function getElementQueryStatusCondition(DbCommand $query, $status)
	{
		switch ($status)
		{
			case SproutEmail_NotificationEmailModel::ENABLED:
			{
				$query->andWhere('elements.enabled = 1');

				break;
			}
			case SproutEmail_NotificationEmailModel::PENDING:
			{
				$query->andWhere('elements.enabled = 1');
				$query->andWhere('notificationemail.template = ""');

				break;
			}
			case SproutEmail_NotificationEmailModel::DISABLED:
			{
				$query->andWhere('elements.enabled = 0');

				break;
			}
		}
	}
}