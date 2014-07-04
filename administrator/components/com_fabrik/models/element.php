<?php
/**
 * Admin Element Model
 *
 * @package     Joomla.Administrator
 * @subpackage  Fabrik
 * @copyright   Copyright (C) 2005-2013 fabrikar.com - All rights reserved.
 * @license     GNU/GPL http://www.gnu.org/copyleft/gpl.html
 * @since       1.6
 */

// No direct access
defined('_JEXEC') or die('Restricted access');

jimport('joomla.application.component.modeladmin');

require_once 'fabmodeladmin.php';

/**
 * Admin Element Model
 *
 * @package     Joomla.Administrator
 * @subpackage  Fabrik
 * @since       3.0
 */

class FabrikAdminModelElement extends FabModelAdmin
{
	/**
	 * The prefix to use with controller messages.
	 *
	 * @var  string
	 */
	protected $text_prefix = 'COM_FABRIK_ELEMENT';

	/**
	 * Validation plugin models for this element
	 *
	 * @since	3.0.6
	 *
	 * @var  array
	 */
	protected $aValidations = null;

	/**
	 * Core Joomla and Fabrik table names
	 *
	 * @var  array
	 */
	protected $core = array('#__assets', '#__banner_clients', '#__banner_tracks', '#__banners', '#__categories', '#__contact_details', '#__content',
		'#__content_frontpage', '#__content_rating', '#__core_log_searches', '#__extensions', '#__fabrik_connections', '#__{package}_cron',
		'#__{package}_elements', '#__{package}_form_sessions', '#__{package}_formgroup', '#__{package}_forms', '#__{package}_groups',
		'#__{package}_joins', '#__{package}_jsactions', '#__{package}_lists', '#__{package}_log', '#__{package}_packages',
		'#__{package}_validations', '#__{package}_visualizations', '#__fb_contact_sample', '#__languages', '#__menu', '#__menu_types', '#__messages',
		'#__messages_cfg', '#__modules', '#__modules_menu', '#__newsfeeds', '#__redirect_links', '#__schemas', '#__session', '#__template_styles',
		'#__update_categories', '#__update_sites', '#__update_sites_extensions', '#__updates', '#__user_profiles', '#__user_usergroup_map',
		'#__usergroups', '#__users', '#__viewlevels', '#__weblinks');

	/**
	 * Constructor.
	 * Ensure that we use the fabrik db model for the dbo
	 *
	 * @param   array  $config  An optional associative array of configuration settings.
	 */

	public function __construct($config = array())
	{
		$config['dbo'] = FabrikWorker::getDbo(true);

		parent::__construct($config);
	}

	/**
	 * Returns a reference to the a Table object, always creating it.
	 *
	 * @param   string  $type    The table type to instantiate
	 * @param   string  $prefix  A prefix for the table class name. Optional.
	 * @param   array   $config  Configuration array for model. Optional.
	 *
	 * @return  JTable  A database object
	 */

	public function getTable($type = 'Element', $prefix = 'FabrikTable', $config = array())
	{
		$config['dbo'] = FabrikWorker::getDbo(true);

		return FabTable::getInstance($type, $prefix, $config);
	}

	/**
	 * Method to get the record form.
	 *
	 * @param   array  $data      Data for the form.
	 * @param   bool   $loadData  True if the form is to load its own data (default case), false if not.
	 *
	 * @return  mixed  A JForm object on success, false on failure
	 */

	public function getForm($data = array(), $loadData = true)
	{
		// Get the form.
		$form = $this->loadForm('com_fabrik.element', 'element', array('control' => 'jform', 'load_data' => $loadData));

		if (empty($form))
		{
			return false;
		}

		$form->model = $this;

		return $form;
	}

	/**
	 * Method to get the data that should be injected in the form.
	 *
	 * @return  mixed   The data for the form.
	 */

	protected function loadFormData()
	{
		// Check the session for previously entered form data.
		$data = JFactory::getApplication()->getUserState('com_fabrik.edit.element.data', array());

		if (empty($data))
		{
			$data = $this->getItem();
		}

		return $data;
	}

	/**
	 * Get elements
	 *
	 * @deprecated since 3.1b2
	 *
	 * @return array
	 */

	public function getElements()
	{
		return array();
	}

	/**
	 * Toggle adding / removing the elment from the list view
	 *
	 * @param   array  &$pks   primary keys
	 * @param   var    $value  add (1) or remove (0) from list view
	 *
	 * @return  bool
	 */

	public function addToListView(&$pks, $value = 1)
	{
		// Initialise variables.
		$dispatcher = JDispatcher::getInstance();
		$user = JFactory::getUser();
		$item = $this->getTable();
		$pks = (array) $pks;

		// Include the content plugins for the change of state event.
		JPluginHelper::importPlugin('content');

		// Access checks.
		foreach ($pks as $i => $pk)
		{
			if ($item->load($pk))
			{
				if (!$this->canEditState($item))
				{
					// Prune items that you can't change.
					unset($pks[$i]);
					JError::raiseWarning(403, FText::_('JLIB_APPLICATION_ERROR_EDIT_STATE_NOT_PERMITTED'));
				}
			}
		}

		// Attempt to change the state of the records.
		if (!$item->addToListView($pks, $value, $user->get('id')))
		{
			$this->setError($item->getError());

			return false;
		}

		$context = $this->option . '.' . $this->name;

		// Trigger the onContentChangeState event.
		$result = $dispatcher->trigger($this->event_change_state, array($context, $pks, $value));

		if (in_array(false, $result, true))
		{
			$this->setError($item->getError());

			return false;
		}

		return true;
	}

	/**
	 * Get the js events that are used by the element
	 *
	 * @return  array
	 */

	public function getJsEvents()
	{
		$db = FabrikWorker::getDbo(true);
		$query = $db->getQuery(true);
		$id = (int) $this->getItem()->id;
		$query->select('*')->from('#__{package}_jsactions')->where('element_id = ' . $id)->order('id');
		$db->setQuery($query);
		$items = $db->loadObjectList();

		for ($i = 0; $i < count($items); $i++)
		{
			$items[$i]->params = json_decode($items[$i]->params);
			$items[$i]->params->js_e_value = htmlspecialchars_decode($items[$i]->params->js_e_value);
		}

		return $items;
	}

	/**
	 * Load the actual validation plugins that the element uses
	 *
	 * @return  array  plugins
	 */

	public function getPlugins()
	{
		$item = $this->getItem();
		$plugins = (array) FArrayHelper::getNestedValue($item->params, 'validations.plugin', array());
		$published = (array) FArrayHelper::getNestedValue($item->params, 'validations.plugin_published', array());
		$icons = (array) FArrayHelper::getNestedValue($item->params, 'validations.show_icon', array());
		$in = (array) FArrayHelper::getNestedValue($item->params, 'validations.validate_in', array());
		$on = (array) FArrayHelper::getNestedValue($item->params, 'validations.validation_on', array());

		$return = array();

		for ($i = 0; $i < count($plugins); $i ++)
		{
			$o = new stdClass;
			$o->plugin = $plugins[$i];
			$o->published = JArrayHelper::getValue($published, $i, 1);
			$o->show_icon = JArrayHelper::getValue($icons, $i, 1);
			$o->validate_in = JArrayHelper::getValue($in, $i, 'both');
			$o->validation_on = JArrayHelper::getValue($on, $i, 'both');
			$return[] = $o;
		}

		return $return;
	}

	/**
	 * Get the js code to build the plugins etc
	 *
	 * @return  string  js code
	 */

	public function getJs()
	{
		$plugins = $this->getPlugins();
		$item = $this->getItem();
		$pluginManager = JModelLegacy::getInstance('Pluginmanager', 'FabrikFEModel');

		$opts = new stdClass;
		$opts->plugin = $item->plugin;
		$opts->parentid = (int) $item->parent_id;
		$opts->jsevents = $this->getJsEvents();
		$opts->id = (int) $item->id;
		$opts->deleteButton = FabrikWorker::j3() ? '<a class="btn btn-danger"><i class="icon-delete"></i> ' : '<a class="removeButton">';
		$opts->deleteButton .= FText::_('COM_FABRIK_DELETE') . '</a>';
		$opts = json_encode($opts);
		JText::script('COM_FABRIK_PLEASE_SELECT');
		JText::script('COM_FABRIK_JS_SELECT_EVENT');
		JText::script('COM_FABRIK_JS_INLINE_JS_CODE');
		JText::script('COM_FABRIK_JS_INLINE_COMMENT_WARNING');
		JText::script('COM_FABRIK_JS_WHEN_ELEMENT');
		JText::script('COM_FABRIK_JS_IS');
		JText::script('COM_FABRIK_JS_NO_ACTION');
		$js[] = "window.addEvent('domready', function () {";
		$js[] = "\tvar opts = $opts;";

		$plugins = json_encode($this->getPlugins());
		$js[] = "\tFabrik.controller = new fabrikAdminElement($plugins, opts, " . (int) $this->getItem()->id . ");";
		$js[] = "})";

		return implode("\n", $js);
	}

	/**
	 * Get html form fields for a plugin (filled with
	 * current element's plugin data
	 *
	 * @param   string  $plugin  plugin name
	 *
	 * @return  string	html form fields
	 */

	public function getPluginHTML($plugin = null)
	{
		$app = JFactory::getApplication();
		$input = $app->input;
		$str = '';
		$item = $this->getItem();

		if (is_null($plugin))
		{
			$plugin = $item->plugin;
		}

		$input->set('view', 'element');
		JPluginHelper::importPlugin('fabrik_element', $plugin);
		$pluginManager = JModelLegacy::getInstance('Pluginmanager', 'FabrikFEModel');

		if ($plugin == '')
		{
			$str = '<div class="alert">' . FText::_('COM_FABRIK_SELECT_A_PLUGIN') . '</div>';
		}
		else
		{
			$plugin = $pluginManager->getPlugIn($plugin, 'Element');
			$mode = FabrikWorker::j3() ? 'nav-tabs' : '';
			$str = $plugin->onRenderAdminSettings(JArrayHelper::fromObject($item), null, $mode);
		}

		return $str;
	}

	/**
	 * Prepare and sanitise the table data prior to saving.
	 *
	 * @param   JTable  $table  A reference to a JTable object.
	 *
	 * @return  void
	 *
	 * @since   12.2
	 */
	protected function prepareTable($table)
	{
	}

	/**
	 * Method to validate the form data.
	 *
	 * @param   JForm   $form   The form to validate against.
	 * @param   array   $data   The data to validate.
	 * @param   string  $group  The name of the field group to validate.
	 *
	 * @see     JFormRule
	 * @see     JFilterInput
	 *
	 * @return  mixed  Array of filtered data if valid, false otherwise.
	 */

	public function validate($form, $data, $group = null)
	{
		$ok = parent::validate($form, $data);
		$app = JFactory::getApplication();
		$input = $app->input;

		// Standard jform validation failed so we shouldn't test further as we can't be sure of the data
		if (!$ok)
		{
			return false;
		}

		$db = FabrikWorker::getDbo(true);
		$elementModel = $this->getElementPluginModel($data);
		$nameChanged = $data['name'] !== $elementModel->getElement()->name;
		$elementModel->getElement()->bind($data);
		$listModel = $elementModel->getListModel();

		if ($data['id'] == '')
		{
			// Have to forcefully set group id otherwise listmodel id is blank
			$elementModel->getElement()->group_id = $data['group_id'];

			if ($listModel->canAddFields() === false && $listModel->noTable() === false)
			{
				$this->setError(FText::_('COM_FABRIK_ERR_CANT_ADD_FIELDS'));
			}

			if (FabrikWorker::isReserved($data['name']))
			{
				$this->setError(FText::_('COM_FABRIK_RESEVED_NAME_USED'));
			}
		}
		else
		{
			if ($listModel->canAlterFields() === false && $nameChanged && $listModel->noTable() === false)
			{
				$this->setError(FText::_('COM_FABRIK_ERR_CANT_ALTER_EXISTING_FIELDS'));
			}

			if ($nameChanged && FabrikWorker::isReserved($data['name'], false))
			{
				$this->setError(FText::_('COM_FABRIK_RESEVED_NAME_USED'));
			}
		}

		$listModel = $elementModel->getListModel();
		/**
		 * Test for duplicate names
		 * unlinking produces this error
		 */
		if (!$input->get('unlink', false) && (int) $data['id'] === 0)
		{
			$query = $db->getQuery(true);
			$query->select('t.id')->from('#__{package}_joins AS j');
			$query->join('INNER', '#__{package}_lists AS t ON j.table_join = t.db_table_name');
			$query->where('group_id = ' . (int) $data['group_id'] . ' AND element_id = 0');
			$db->setQuery($query);
			$joinTblId = (int) $db->loadResult();
			$ignore = array($data['id']);

			if ($joinTblId === 0)
			{
				if ($listModel->fieldExists($data['name'], $ignore))
				{
					$this->setError(FText::_('COM_FABRIK_ELEMENT_NAME_IN_USE'));
				}
			}
			else
			{
				$joinListModel = JModelLegacy::getInstance('list', 'FabrikFEModel');
				$joinListModel->setId($joinTblId);
				$joinEls = $joinListModel->getElements();

				foreach ($joinEls as $joinEl)
				{
					if ($joinEl->getElement()->name == $data['name'])
					{
						$ignore[] = $joinEl->getElement()->id;
					}
				}

				if ($joinListModel->fieldExists($data['name'], $ignore))
				{
					$this->setError(FText::_('COM_FABRIK_ELEMENT_NAME_IN_USE'));
				}
			}
		}
		// Strip <p> tag from label
		$data['label'] = JString::str_ireplace(array('<p>', '</p>'), '', $data['label']);

		return count($this->getErrors()) == 0 ? $data : false;
	}

	/**
	 * Load the element plugin / model for the posted data
	 *
	 * @param   array  $data  posted data
	 *
	 * @return  object  element model
	 */

	private function getElementPluginModel($data)
	{
		$pluginManager = JModelLegacy::getInstance('Pluginmanager', 'FabrikFEModel');
		$id = $data['id'];
		$elementModel = $pluginManager->getPlugIn($data['plugin'], 'element');
		/**
		 * $$$ rob f3 - need to bind the data in here otherwise validate fails on dup name test (as no group_id set)
		 * $$$ rob 29/06/2011 removed as you can't then test name changes in validate() so now bind should be done after
		 * getElementPluginModel is called.
		 * $elementModel->getElement()->bind($data);
		 */
		$elementModel->setId($id);

		return $elementModel;
	}

	/**
	 * Method to save the form data.
	 *
	 * @param   array  $data  The form data.
	 *
	 * @return  boolean  True on success, False on error.
	 */

	public function save($data)
	{
		$config = JComponentHelper::getParams('com_fabrik');

		if ($config->get('fbConf_wysiwyg_label', 0) == 0)
		{
			// Ensure the data is in the same format as when saved by the wysiwyg element e.g. < becomes &lt;
			$data['label'] = htmlspecialchars($data['label']);
		}

		jimport('joomla.utilities.date');
		$user = JFactory::getUser();
		$app = JFactory::getApplication();
		$input = $app->input;
		$new = $data['id'] == 0 ? true : false;
		$params = $data['params'];
		$data['name'] = FabrikString::iclean($data['name']);
		$name = $data['name'];
		$params['validations'] = JArrayHelper::getValue($data, 'validationrule', array());
		$elementModel = $this->getElementPluginModel($data);
		$elementModel->getElement()->bind($data);
		$origId = $input->getInt('id');
		$row = $elementModel->getElement();

		if ($new)
		{
			// Have to forcefully set group id otherwise listmodel id is blank
			$elementModel->getElement()->group_id = $data['group_id'];
		}

		$listModel = $elementModel->getListModel();
		$item = $listModel->getTable();

		// Are we updating the name of the primary key element?

		if ($row->name === FabrikString::shortColName($item->db_primary_key))
		{
			if ($name !== $row->name)
			{
				// Yes we are so update the table
				$item->db_primary_key = str_replace($row->name, $name, $item->db_primary_key);
				$item->store();
			}
		}

		$jsons = array('sub_values', 'sub_labels', 'sub_initial_selection');

		foreach ($jsons as $json)
		{
			if (array_key_exists($json, $data))
			{
				$data[$json] = json_encode($data[$json]);
			}
		}
		// Only update the element name if we can alter existing columns, otherwise the name and field name become out of sync
		$data['name'] = ($listModel->canAlterFields() || $new || $listModel->noTable()) ? $name : $input->get('name_orig', '');

		$ar = array('published', 'use_in_page_title', 'show_in_list_summary', 'link_to_detail', 'can_order', 'filter_exact_match');

		foreach ($ar as $a)
		{
			if (!array_key_exists($a, $data))
			{
				$data[$a] = 0;
			}
		}

		/**
		 * $$$ rob - test for change in element type
		 * (eg if changing from db join to field we need to remove the join
		 * entry from the #__{package}_joins table
		 */
		$elementModel->beforeSave($row);

		// Unlink linked elements
		if ($input->get('unlink') == 'on')
		{
			$data['parent_id'] = 0;
		}

		$datenow = new JDate;

		if ($row->id != 0)
		{
			$data['modified'] = $datenow->toSql();
			$data['modified_by'] = $user->get('id');
		}
		else
		{
			$data['created'] = $datenow->toSql();
			$data['created_by'] = $user->get('id');
			$data['created_by_alias'] = $user->get('username');
		}

		/**
		 * $$$ hugh
		 * This insane chunk of code is needed because the validation rule params are not in sequential,
		 * completely indexed arrays.  What we have is single item arrays, with specific numeric
		 * keys, like foo-something[0], bar-otherthing[2], etc.  And if you json_encode an array with incomplete
		 * or out of sequence numeric indexes, it encodes it as an object instead of an array.  Which means the first
		 * validation plugin will encode as an array, as it's params are single [0] index, and the rest as objects.
		 * This foobars things, as we then don't know if validation params are arrays or objects!
		 *
		 * One option would be to modify every validation, and test every param we use, and if necessary convert it,
		 * but that would be a major pain in the ass.
		 *
		 * So ... we need to fill in the blanks in the arrays, and ksort them.  But, we need to know the param names
		 * for each validation.  But as they are just stuck in with the rest of the element params, there is no easy
		 * way of knowing which are validation params and which are element params.
		 *
		 * So ... we need to load the validation objects, then load the XML file for each one, and iterate through
		 * the fieldsets!  Well, that's the only way I could come up with doing it.  Hopefully Rob can come up with
		 * a quicker and simpler way of doing this!
		 */
		$validations = JArrayHelper::getValue($params['validations'], 'plugin', array());
		$num_validations = count($validations);
		$validation_plugins = $this->getValidations($elementModel, $validations);

		foreach ($validation_plugins as $plugin)
		{
			$plugin_form = $plugin->getJForm();
			JForm::addFormPath(JPATH_SITE . '/plugins/fabrik_validationrule/' . $plugin->get('pluginName'));
			$xmlFile = JPATH_SITE . '/plugins/fabrik_validationrule/' . $plugin->get('pluginName') . '/forms/fields.xml';
			$xml = $plugin->jform->loadFile($xmlFile, false);

			foreach ($plugin_form->getFieldsets() as $fieldset)
			{
				foreach ($plugin_form->getFieldset($fieldset->name) as $field)
				{
					if (isset($params[$field->fieldname]))
					{
						if (is_array($params[$field->fieldname]))
						{
							for ($x = 0; $x < $num_validations; $x++)
							{
								if (!(array_key_exists($x, $params[$field->fieldname])))
								{
									$params[$field->fieldname][$x] = '';
								}
							}

							ksort($params[$field->fieldname]);
						}
					}
				}
			}
		}

		$data['params'] = json_encode($params);
		$row->params = $data['params'];
		$cond = 'group_id = ' . (int) $row->group_id;

		if ($new)
		{
			$data['ordering'] = $row->getNextOrder($cond);
		}

		$row->reorder($cond);
		/**
		 * $$$ hugh - shouldn't updateChildIds() happen AFTER we save the main row?
		 * Same place we do updateJavascript()?
		 */
		$this->updateChildIds($row);
		$elementModel->getElement()->bind($data);
		$origName = $input->get('name_orig', '');
		list($update, $q, $oldName, $newdesc, $origDesc) = $listModel->shouldUpdateElement($elementModel, $origName);

		if ($update && $input->get('task') !== 'save2copy')
		{
			$origplugin = $input->get('plugin_orig');
			$config = JFactory::getConfig();
			$prefix = $config->get('dbprefix');
			$tablename = $listModel->getTable()->db_table_name;
			$hasprefix = (strstr($tablename, $prefix) === false) ? false : true;
			$tablename = str_replace($prefix, '#__', $tablename);

			if (in_array($tablename, $this->core))
			{
				$app->enqueueMessage(FText::_('COM_FABRIK_WARNING_UPDATE_CORE_TABLE'), 'notice');
			}
			else
			{
				if ($hasprefix)
				{
					$app->enqueueMessage(FText::_('COM_FABRIK_WARNING_UPDATE_TABLE_WITH_PREFIX'), 'notice');
				}
			}

			$app->setUserState('com_fabrik.confirmUpdate', 1);

			$app->setUserState('com_fabrik.plugin_orig', $origplugin);
			$app->setUserState('com_fabrik.q', $q);
			$app->setUserState('com_fabrik.newdesc', $newdesc);
			$app->setUserState('com_fabrik.origDesc', $origDesc);

			$app->setUserState('com_fabrik.origplugin', $origplugin);
			$app->setUserState('com_fabrik.oldname', $oldName);
			$app->setUserState('com_fabrik.newname', $data['name']);
			$app->setUserState('com_fabrik.origtask', $input->get('task'));
			$app->setUserState('com_fabrik.plugin', $data['plugin']);
			$task = $input->get('task');
			$url = 'index.php?option=com_fabrik&view=element&layout=confirmupdate&id=' . (int) $origId . '&origplugin=' . $origplugin . '&origtask='
				. $task . '&plugin=' . $row->plugin;
			$app->setUserState('com_fabrik.redirect', $url);
		}
		else
		{
			$app->setUserState('com_fabrik.confirmUpdate', 0);
		}

		if ((int) $listModel->getTable()->id !== 0)
		{
			$this->updateIndexes($elementModel, $listModel, $row);
		}

		$return = parent::save($data);

		if ($return)
		{
			$this->updateJavascript($data);
			$elementModel->setId($this->getState($this->getName() . '.id'));
			$row->id = $elementModel->getId();
			$data['id'] = $row->id;
			$this->createRepeatElement($elementModel, $row);

			// If new, check if the element's db table is used by other tables and if so add the element  to each of those tables' groups
			if ($new)
			{
				$this->addElementToOtherDbTables($elementModel, $row);
			}

			if (!$elementModel->onSave($data))
			{
				$this->setError(FText::_('COM_FABRIK_ERROR_SAVING_ELEMENT_PLUGIN_OPTIONS'));

				return false;
			}
		}

		parent::cleanCache('com_fabrik');

		return $return;
		/**
		 * used for prefab
		 * return $elementModel;
		 */
	}

	/**
	 * When saving an element, it may need to be added to other Fabrik lists
	 * If those lists point to the same database table.
	 *
	 * @param   object  $elementModel  element
	 * @param   object  $row           item
	 *
	 * @return  void
	 */

	private function addElementToOtherDbTables($elementModel, $row)
	{
		$db = FabrikWorker::getDbo(true);
		$list = $elementModel->getListModel()->getTable();
		$origElid = $row->id;
		$tmpgroupModel = $elementModel->getGroup();
		$config = JComponentHelper::getParams('com_fabrik');

		if ($tmpgroupModel->isJoin())
		{
			$dbname = $tmpgroupModel->getJoinModel()->getJoin()->table_join;
		}
		else
		{
			$dbname = $list->db_table_name;
		}

		$query = $db->getQuery(true);
		$query->select("DISTINCT(l.id) AS id, db_table_name, l.label, l.form_id, l.label AS form_label, g.id AS group_id");
		$query->from("#__{package}_lists AS l");
		$query->join('INNER', '#__{package}_forms AS f ON l.form_id = f.id');
		$query->join('LEFT', '#__{package}_formgroup AS fg ON f.id = fg.form_id');
		$query->join('LEFT', '#__{package}_groups AS g ON fg.group_id = g.id');
		$query->where("db_table_name = " . $db->quote($dbname) . " AND l.id !=" . (int) $list->id . " AND is_join = 0");

		$db->setQuery($query);
		$othertables = $db->loadObjectList('id');

		/**
		 * $$$ rob 20/02/2012 if you have 2 lists, counters, regions and then you join regions to countries to get a new group "countries - [regions]"
		 * Then add elements to the regions list, the above query wont find the group "countries - [regions]" to add the elements into
		 */

		$query->clear();
		$query->select('DISTINCT(l.id) AS id, l.db_table_name, l.label, l.form_id, l.label AS form_label, fg.group_id AS group_id')
			->from('#__{package}_joins AS j')->join('LEFT', '#__{package}_formgroup AS fg ON fg.group_id = j.group_id')
			->join('LEFT', '#__{package}_forms AS f ON fg.form_id = f.id')->join('LEFT', '#__{package}_lists AS l ON l.form_id = f.id')
			->where('j.table_join = ' . $db->quote($dbname) . ' AND j.list_id <> 0 AND j.element_id = 0 AND list_id <> ' . (int) $list->id);
		$db->setQuery($query);
		$joinedLists = $db->loadObjectList('id');
		$othertables = array_merge($joinedLists, $othertables);

		if (!empty($othertables))
		{
			/**
			 * $$$ hugh - we use $row after this, so we need to work on a copy, otherwise
			 * (for instance) we redirect to the wrong copy of the element
			 */
			$rowcopy = clone ($row);

			foreach ($othertables as $listid => $t)
			{
				$rowcopy->id = 0;
				$rowcopy->parent_id = $origElid;
				$rowcopy->group_id = $t->group_id;
				$rowcopy->name = str_replace('`', '', $rowcopy->name);

				if ($config->get('unpublish_clones', false))
				{
					$rowcopy->published = 0;
				}

				$rowcopy->store();

				// Copy join records
				$join = $this->getTable('join');

				if ($join->load(array('element_id' => $origElid)))
				{
					$join->id = 0;
					unset($join->id);
					$join->element_id = $rowcopy->id;
					$join->list_id = $listid;
					$join->store();
				}
			}
		}
	}

	/**
	 * Update child elements
	 *
	 * @param   object  &$row  element
	 *
	 * @return  mixed
	 */

	private function updateChildIds(&$row)
	{
		if ((int) $row->id === 0)
		{
			// New element so don't update child ids

			return;
		}

		$ids = $this->getElementDescendents($row->id);
		$ignore = array('_tbl', '_tbl_key', '_db', 'id', 'group_id', 'created', 'created_by', 'parent_id', 'ordering');
		$pluginManager = JModelLegacy::getInstance('Pluginmanager', 'FabrikFEModel');

		foreach ($ids as $id)
		{
			$plugin = $pluginManager->getElementPlugin($id);
			$leave = $plugin->getFixedChildParameters();
			$item = $plugin->getElement();

			foreach ($row as $key => $val)
			{
				if (!in_array($key, $ignore))
				{
					if ($key == 'params')
					{
						$origParams = json_decode($item->params);
						$newParams = json_decode($val);

						foreach ($newParams as $pKey => $pVal)
						{
							if (!in_array($pKey, $leave))
							{
								$origParams->$pKey = $pVal;
							}
						}

						$val = json_encode($origParams);
					}
					else
					{
						// $$$rob - i can't replicate bug #138 but this should fix things anyway???
						if ($key == 'name')
						{
							$val = str_replace("`", '', $val);
						}
					}

					$item->$key = $val;
				}
			}

			if (!$item->store())
			{
				JError::raiseWarning(500, $item->getError());
			}
		}

		return true;
	}

	/**
	 * Update table indexes based on element settings
	 *
	 * @param   object  &$elementModel  element model
	 * @param   object  &$listModel     list model
	 * @param   object  &$row           element item
	 *
	 * @return  void
	 */

	private function updateIndexes(&$elementModel, &$listModel, &$row)
	{
		if ($elementModel->getGroup()->isJoin())
		{
			return;
		}
		// Update table indexes
		$ftype = $elementModel->getFieldDescription();

		// Int elements can't have a index size attrib
		$size = JString::stristr($ftype, 'int') || $ftype == 'DATETIME' ? '' : '10';

		if ($elementModel->getParams()->get('can_order'))
		{
			$listModel->addIndex($row->name, 'order', 'INDEX', $size);
		}
		else
		{
			$listModel->dropIndex($row->name, 'order', 'INDEX');
		}

		if ($row->filter_type != '')
		{
			$listModel->addIndex($row->name, 'filter', 'INDEX', $size);
		}
		else
		{
			$listModel->dropIndex($row->name, 'filter', 'INDEX');
		}
	}

	/**
	 * Delete old javascript actions for the element
	 * & add new javascript actions
	 *
	 * @param   array  $data  to save
	 *
	 * @return void
	 */

	protected function updateJavascript($data)
	{
		/**
		 * $$$ hugh - 2012/04/02
		 * updated to apply js changes to descendants as well.  NOTE that this means
		 * all descendants (i.e. children of children, etc.), not just direct children.
		 */
		$app = JFactory::getApplication();
		$input = $app->input;
		$this_id = $this->getState($this->getName() . '.id');
		$ids = $this->getElementDescendents($this_id);
		$ids[] = $this_id;
		$db = FabrikWorker::getDbo(true);
		$query = $db->getQuery(true);
		$query->delete('#__{package}_jsactions')->where('element_id IN (' . implode(',', $ids) . ')');
		$db->setQuery($query);
		$db->execute();
		$jform = $input->get('jform', array(), 'array');
		$eEvent = JArrayHelper::getValue($jform, 'js_e_event', array());
		$eTrigger = JArrayHelper::getValue($jform, 'js_e_trigger', array());
		$eCond = JArrayHelper::getValue($jform, 'js_e_condition', array());
		$eVal = JArrayHelper::getValue($jform, 'js_e_value', array());
		$ePublished = JArrayHelper::getValue($jform, 'js_published', array());
		$action = (array) JArrayHelper::getValue($jform, 'action', array());

		foreach ($action as $c => $jsAction)
		{
			if ($jsAction === '')
			{
				continue;
			}

			$params = new stdClass;
			$params->js_e_event = $eEvent[$c];
			$params->js_e_trigger = $eTrigger[$c];
			$params->js_e_condition = $eCond[$c];
			$params->js_e_value = htmlspecialchars($eVal[$c]);
			$params->js_published = $ePublished[$c];
			$params = json_encode($params);
			$code = $jform['code'][$c];
			$code = htmlspecialchars($code, ENT_QUOTES);

			foreach ($ids as $id)
			{
				$query = $db->getQuery(true);
				$query->insert('#__{package}_jsactions');
				$query->set('element_id = ' . (int) $id);
				$query->set('action = ' . $db->quote($jsAction));
				$query->set('code = ' . $db->quote($code));
				$query->set('params = \'' . $params . "'");
				$db->setQuery($query);
				$db->execute();
			}
		}
	}

	/**
	 * Take an array of group ids and return the corresponding element
	 * used in list publish code
	 *
	 * @param   array  $ids  group ids
	 *
	 * @return  array  element ids
	 */

	public function swapGroupToElementIds($ids = array())
	{
		if (empty($ids))
		{
			return array();
		}

		JArrayHelper::toInteger($ids);
		$db = FabrikWorker::getDbo(true);
		$query = $db->getQuery(true);
		JArrayHelper::toInteger($ids);
		$query->select('id')->from('#__{package}_elements')->where('group_id IN (' . implode(',', $ids) . ')');

		return $db->setQuery($query)->loadColumn();
	}

	/**
	 * Potentially drop fields then remove element record
	 *
	 * @param   array  &$pks  To delete
	 *
	 * @return  boolean  True if successful, false if an error occurs.
	 */

	public function delete(&$pks)
	{
		// Initialize variables
		$app = JFactory::getApplication();
		$input = $app->input;
		$pluginManager = JModelLegacy::getInstance('Pluginmanager', 'FabrikFEModel');
		$elementIds = $app->input->get('elementIds', array(), 'array');

		foreach ($elementIds as $id)
		{
			if ((int) $id === 0)
			{
				continue;
			}

			$pluginModel = $pluginManager->getElementPlugin($id);
			$pluginModel->onRemove($id);
			$element = $pluginModel->getElement();

			if ($pluginModel->isRepeatElement())
			{
				$listModel = $pluginModel->getListModel();
				$db = $listModel->getDb();
				$tableName = $db->quoteName($this->getRepeatElementTableName($pluginModel));
				$sql = 'DROP TABLE ' . $tableName;
				if($listModel->suggestOnly())
				{
					// FIXME: Better dialog
					JError::raiseNotice( 100, "Fabrik suggest this change (drop table): $sql" );
				}
				else
				{
					$db->setQuery($sql);
					$db->execute();
				}
			}

			$listModel = $pluginModel->getListModel();
			$item = $listModel->getTable();

			// $$$ hugh - might be a table-less form!
			if (!empty($item->id))
			{
				$db = $listModel->getDb();
				$sql = 'ALTER TABLE ' . $db->quoteName($item->db_table_name) . ' DROP ' . $db->quoteName($element->name)
				if($listModel->suggestOnly())
				{
					// FIXME: Better dialog
					JError::raiseNotice( 100, "Fabrik suggest this change (drop column): $sql" );
				}
				else
				{
					$db->setQuery($sql);
					$db->execute();
				}
			}
		}

		return parent::delete($pks);
	}

	/**
	 * Copy an element
	 *
	 * @return  mixed  true or warning
	 */

	public function copy()
	{
		$app = JFactory::getApplication();
		$input = $app->input;
		$cid = $input->get('cid', array(), 'array');
		JArrayHelper::toInteger($cid);
		$names = $input->get('name', array(), 'array');
		$rule = $this->getTable('element');

		foreach ($cid as $id => $groupid)
		{
			if ($rule->load((int) $id))
			{
				$name = JArrayHelper::getValue($names, $id, $rule->name);
				$data = JArrayHelper::fromObject($rule);
				$elementModel = $this->getElementPluginModel($data);
				$elementModel->getElement()->bind($data);
				$newrule = $elementModel->copyRow($id, $rule->label, $groupid, $name);
				$data = JArrayHelper::fromObject($newrule);
				$elementModel = $this->getElementPluginModel($data);
				$elementModel->getElement()->bind($data);
				$listModel = $elementModel->getListModel();
				$res = $listModel->shouldUpdateElement($elementModel);
				$this->addElementToOtherDbTables($elementModel, $rule);
			}
			else
			{
				return JError::raiseWarning(500, $rule->getError());
			}
		}

		return true;
	}

	/**
	 * If repeated element we need to make a joined db table to store repeated data in
	 *
	 * @param   object  $elementModel  element model
	 * @param   object  $row           element item
	 *
	 * @return  void
	 */

	public function createRepeatElement($elementModel, $row)
	{
		if (!$elementModel->isJoin())
		{
			return;
		}

		$row->name = str_replace('`', '', $row->name);
		$listModel = $elementModel->getListModel();
		$tableName = $this->getRepeatElementTableName($elementModel, $row);

		// Create db table!
		$formModel = $elementModel->getForm();
		$db = $listModel->getDb();
		$desc = $elementModel->getFieldDescription();
		$name = $db->quoteName($row->name);
		$sql = 'CREATE TABLE IF NOT EXISTS ' . $db->quoteName($tableName) . ' ( id INT( 10 ) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, parent_id INT(10) UNSIGNED, '
				. $name . ' ' . $desc . ', ' . $db->quoteName('params') . ' TEXT );';
		if($listModel->suggestOnly())
		{
			// FIXME: Better dialog
			JError::raiseNotice( 100, "Fabrik suggest this change (create repeat element): $sql" );
		}
		else
		{
			$db->setQuery($sql);
			$db->execute();
		}

		// Remove previous join records if found
		if ((int) $row->id !== 0)
		{
			$jdb = FabrikWorker::getDbo(true);
			$query = $jdb->getQuery(true);
			$query->delete('#__{package}_joins')->where('element_id = ' . (int) $row->id);
			$jdb->setQuery($query);
			$jdb->execute();
		}
		// Create or update fabrik join
		$data = array('list_id' => $listModel->getTable()->id, 'element_id' => $row->id, 'join_from_table' => $listModel->getTable()->db_table_name,
			'table_join' => $tableName, 'table_key' => $row->name, 'table_join_key' => 'parent_id', 'join_type' => 'left');
		$join = $this->getTable('join');
		$join->load(array('element_id' => $data['element_id']));
		$opts = new stdClass;
		$opts->type = 'repeatElement';
		$data['params'] = json_encode($opts);
		$join->bind($data);
		$join->store();
	}

	/**
	 * Get the name of the repeated elements table
	 *
	 * @param   object  $elementModel  element model
	 * @param   object  $row           element item
	 *
	 * @return  string	table name
	 */

	protected function getRepeatElementTableName($elementModel, $row = null)
	{
		$listModel = $elementModel->getListModel();

		if (is_null($row))
		{
			$row = $elementModel->getElement();
		}

		$origTableName = $listModel->getTable()->db_table_name;

		return $origTableName . '_repeat_' . str_replace('`', '', $row->name);
	}

	/**
	 * Gets the element's parent element
	 *
	 * @return  mixed	0 if no parent, object if exists.
	 */

	public function getParent()
	{
		$item = $this->getItem();
		$item->parent_id = (int) $item->parent_id;

		if ($item->parent_id === 0)
		{
			$parent = 0;
		}
		else
		{
			$db = FabrikWorker::getDbo(true);
			$query = $db->getQuery(true);
			$query->select('*')->from('#__{package}_elements')->where('id = ' . (int) $item->parent_id);
			$db->setQuery($query);
			$parent = $db->loadObject();

			if (is_null($parent))
			{
				// Perhaps the parent element was deleted?
				$parent = 0;
				$item->parent_id = 0;
			}
		}

		return $parent;
	}

	/**
	 * A protected method to get a set of ordering conditions.
	 *
	 * @param   object  $table  A JTable object.
	 *
	 * @return  array  An array of conditions to add to ordering queries.
	 *
	 * @since   Fabrik 3.0b
	 */

	protected function getReorderConditions($table)
	{
		return array('group_id = ' . $table->group_id);
	}

	/**
	 * Recursively get all linked children of an element
	 *
	 * @param   int  $id  element id
	 *
	 * @return  array
	 */

	protected function getElementDescendents($id = 0)
	{
		if (empty($id))
		{
			$id = $this->getState($this->getName() . '.id');
		}

		$db = FabrikWorker::getDbo(true);
		$query = $db->getQuery(true);
		$query->select('id')->from('#__{package}_elements')->where('parent_id = ' . (int) $id);
		$db->setQuery($query);
		$kids = $db->loadObjectList();
		$all_kids = array();

		foreach ($kids as $kid)
		{
			$all_kids[] = $kid->id;
			$all_kids = array_merge($this->getElementDescendents($kid->id), $all_kids);
		}

		return $all_kids;
	}

	/**
	 * Loads in elements validation objects
	 * $$$ hugh - trying to fix issue on saving where we have to massage the plugin
	 * params, which means knowing all the param names, but we can't call the FE model
	 * version of this method 'cos ... well, it breaks.
	 *
	 * @param   object  $elementModel  a front end element model
	 * @param   array   $usedPlugins   an array of validation plugin names to load
	 *
	 * @return  array	validation objects
	 */

	private function getValidations($elementModel, $usedPlugins = array())
	{
		if (isset($this->_aValidations))
		{
			return $this->_aValidations;
		}

		$pluginManager = FabrikWorker::getPluginManager();
		$pluginManager->getPlugInGroup('validationrule');
		$this->aValidations = array();

		$dispatcher = JDispatcher::getInstance();
		$ok = JPluginHelper::importPlugin('fabrik_validationrule');

		foreach ($usedPlugins as $usedPlugin)
		{
			if ($usedPlugin !== '')
			{
				$class = 'plgFabrik_Validationrule' . JString::ucfirst($usedPlugin);
				$conf = array();
				$conf['name'] = JString::strtolower($usedPlugin);
				$conf['type'] = JString::strtolower('fabrik_Validationrule');
				$plugIn = new $class($dispatcher, $conf);
				$oPlugin = JPluginHelper::getPlugin('fabrik_validationrule', $usedPlugin);
				$plugIn->elementModel = $elementModel;
				$this->aValidations[] = $plugIn;
			}
		}

		return $this->aValidations;
	}
}
