<?php

	if( !defined('__IN_SYMPHONY__') ) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');



	require_once(EXTENSIONS.'/frontend_localisation/extension.driver.php');
	require_once(EXTENSIONS.'/frontend_localisation/lib/class.FLang.php');
	require_once(EXTENSIONS.'/page_lhandles/lib/class.PLHManagerURL.php');
	require_once TOOLKIT . '/fields/field.date.php';
	//calendar class taken from the datetime
	require_once(EXTENSIONS . '/datetime/lib/class.calendar.php');



	class FieldMultilingual_Publish_Date extends Field 
	{

		/*------------------------------------------------------------------------------------------------*/
		/*  Definition  */
		/*------------------------------------------------------------------------------------------------*/

		public function __construct(){
			parent::__construct();

			$this->_name = 'Multilingual Publish Date';
			$this->_driver = ExtensionManager::create('multilingual_publish_date');
		}

		public function createTable(){
			$query = "
				CREATE TABLE IF NOT EXISTS `tbl_entries_data_{$this->get('id')}` (
					`id` INT(11) UNSIGNED NOT null AUTO_INCREMENT,
					`entry_id` INT(11) UNSIGNED NOT null,
					`date` datetime DEFAULT null,";

			foreach( FLang::getLangs() as $lc ){
				$query .= sprintf("`date-%s` datetime DEFAULT null,", $lc);
			}

			$query .= "
					PRIMARY KEY (`id`),
					KEY `entry_id` (`entry_id`),";

			foreach( FLang::getLangs() as $lc ){
				$query .= sprintf('KEY `date-%1$s` (`date-%1$s`),', $lc);
			}

			$query .= "
					KEY `date` (`date`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

			return Symphony::Database()->query($query);
		}


		/*-------------------------------------------------------------------------
			Settings:
		-------------------------------------------------------------------------*/
		
		public function displaySettingsPanel(&$wrapper, $errors = null) {
			parent::displaySettingsPanel($wrapper, $errors);
			
			$order = $this->get('sortorder');
			
			//instead of an actual text-box I should do a drop-down of fields in the particular section, will make everything look so much neater :)

			$label = Widget::Label(__('Publish Field'));
			$label->appendChild(Widget::Input(
				"fields[{$order}][publish_field]",
				$this->get('publish_field')
			));
			$wrapper->appendChild($label);
			
			$label = Widget::Label(__('Date Field'));
			$label->appendChild(Widget::Input(
				"fields[{$order}][date_field]",
				$this->get('date_field')
			));			
			$help = new XMLElement('p', __('Please insert the field ids in the above fields, will eventually replace with a selectbox'));
			$help->setAttribute('class', 'help');
			$wrapper->appendChild($label);
			
			$label = Widget::Label();
			$input = Widget::Input("fields[{$order}][hide]", 'yes', 'checkbox');
			if ($this->get('hide') == 'yes') $input->setAttribute('checked', 'checked');
			$label->setValue($input->generate() . ' ' . __('Hide this field on publish page'));
			$wrapper->appendChild($label);
			
			$this->appendShowColumnCheckbox($wrapper);
			
		}
		
		public function commit() {
			if (!parent::commit()) return false;
			
			$id = $this->get('id');
			$handle = $this->handle();
			
			if ($id === false) return false;
			
			$fields = array(
				'field_id'			=> $id,
				'publish_field'		=> $this->get('publish_field'),
				'date_field'		=> $this->get('date_field'),
				'hide'				=> $this->get('hide')
			);
			
			Symphony::Database()->query("DELETE FROM `tbl_fields_{$handle}` WHERE `field_id` = '{$id}' LIMIT 1");
			return Symphony::Database()->insert($fields, "tbl_fields_{$handle}");
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Publish  */
		/*------------------------------------------------------------------------------------------------*/

		public function displayPublishPanel(&$wrapper, $data = null, $flagWithError = null, $prefix = null, $postfix = null){
			if( $this->get('hide') === 'yes' ) return;

			Extension_Frontend_Localisation::appendAssets();

			$main_lang = FLang::getMainLang();
			$all_langs = FLang::getAllLangs();
			$langs = FLang::getLangs();

			$wrapper->setAttribute('class', $wrapper->getAttribute('class').' field-multilingual_entry_url field-multilingual');
			$container = new XMLElement('div', null, array('class' => 'container'));


			/*------------------------------------------------------------------------------------------------*/
			/*  Label  */
			/*------------------------------------------------------------------------------------------------*/

			$label = Widget::Label($this->get('label'));
			if( $this->get('required') != 'yes' ) $label->appendChild(new XMLElement('i', __('Optional')));
			$container->appendChild($label);


			/*------------------------------------------------------------------------------------------------*/
			/*  Tabs  */
			/*------------------------------------------------------------------------------------------------*/

			$ul = new XMLElement('ul', null, array('class' => 'tabs'));
			foreach( $langs as $lc ){
				$li = new XMLElement('li', $all_langs[$lc], array('class' => $lc));
				$lc === $main_lang ? $ul->prependChild($li) : $ul->appendChild($li);
			}

			$container->appendChild($ul);


			/*------------------------------------------------------------------------------------------------*/
			/*  Panels  */
			/*------------------------------------------------------------------------------------------------*/

			$callback = Administration::instance()->getPageCallback();
			$entry_id = $callback['context']['entry_id'];
			$element_name = $this->get('element_name');

			foreach( $langs as $lc ){
				$div = new XMLElement('div', null, array('class' => 'tab-panel tab-'.$lc));

				$span = new XMLElement('span', null);

				if( is_null($entry_id) || is_null($data['date-'.$lc])){
					$span->setValue(__('The entry has not been published yet'));
					$span->setAttribute('class', 'inactive');

				} else{
					$input = Widget::Input(
						"fields{$prefix}[$element_name]{$postfix}[{$lc}]", General::sanitize($data['date-'.$lc])
					);
					$span->appendChild($input);
					// $span->setValue((string)$data['date-'.$lc]);
					$span->setAttribute('class', 'inactive');
				}

				$div->appendChild($span);
				$container->appendChild($div);
			}


			/*------------------------------------------------------------------------------------------------*/
			/*  Error  */
			/*------------------------------------------------------------------------------------------------*/

			if( !is_null($flagWithError) ){
				$wrapper->appendChild(Widget::Error($container, $flagWithError));
			}
			else{
				$wrapper->appendChild($container);
			}
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Input  */
		/*------------------------------------------------------------------------------------------------*/

		public function checkPostFieldData($data, &$message, $entry_id = null) {
			$this->_driver->registerField($this);

			return self::__OK__;
		}

		public function processRawFieldData($data, &$status, &$message, $simulate = false, $entry_id = null){
			// $result = parent::processRawFieldData($data, $status, $message, $simulate, $entry_id);
			$result = array();

			foreach( FLang::getLangs() as $lc ){
				//will need to check if value existed before (if so keep), else take between current and published date if article is set to published
				//maybe here we should just keep the old / null
				if (isset($data[$lc]) && $data[$lc]!='')
					$result['date-'.$lc] = $data[$lc];
				else {
					$result['date-'.$lc] = null;
				}
			}

			return $result;
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Output  */
		/*------------------------------------------------------------------------------------------------*/

		public function appendFormattedElement(&$wrapper, $data, $encode = false){

			$lc = FLang::getLangCode();

			if( empty($lc) ){
				$lc = FLang::getMainLang();
			}

			// Start date
			$date = new DateTime(empty($lc) ? $data['date'] : $data['date-'.$lc]);

			$element = new XMLElement($this->get('element_name'));

			$formattedElement = new XMLElement(
				'date',
				$date->format('Y-m-d'),
				array(
					'iso' => $date->format('c'),
					'time' => $date->format('H:i'),
					'weekday' => $date->format('N'),
					'offset' => $date->format('O')
				)
			);
			$element->appendChild($formattedElement);

			$date = new DateTime($data['date']);
			$formattedElement = new XMLElement(
				'original',
				$date->format('Y-m-d'),
				array(
					'iso' => $date->format('c'),
					'time' => $date->format('H:i'),
					'weekday' => $date->format('N'),
					'offset' => $date->format('O')
				)
			);
			$element->appendChild($formattedElement);

			$wrapper->appendChild($element);
		}

		public function prepareTableValue($data, XMLElement $link = null){
			if( empty($data) ) return;

			$lc = Lang::get();

			if( !FLang::validateLangCode($lc) )
				$lc = FLang::getLangCode();

			$publishedDate = empty($lc) ? $data['date'] : $data['date-'.$lc];
			if (!isset($publishedDate)) return '';
			$date = new DateTime( $publishedDate );
			$formatted = LANG::localizeDate($date->format(__SYM_DATETIME_FORMAT__));

			return $formatted;
		}

		/*-------------------------------------------------------------------------
			Sorting:
		-------------------------------------------------------------------------*/

		function isSortable(){
			return true;
		}

		function buildSortingSQL(&$joins, &$where, &$sort, $order='ASC') {
			$field_id = $this->get('id');

			$lc = Lang::get();

			if( !FLang::validateLangCode($lc) )
				$lc = FLang::getLangCode();

			$date = 'date-'.$lc;
			// If we already have a JOIN to the entry table, don't create another one,
			// this prevents issues where an entry with multiple dates is returned multiple
			// times in the SQL, but is actually the same entry.
			if(!preg_match('/`t' . $field_id . '`/', $joins)) {
				$joins .= "LEFT OUTER JOIN `tbl_entries_data_" . $field_id . "` AS `ed` ON (`e`.`id` = `ed`.`entry_id`) ";
				$sort = 'ORDER BY ' . (in_array(strtolower($order), array('random', 'rand')) ? 'RAND()' : "`ed`.`".$date."` $order");
			}
			else {
				$sort = 'ORDER BY ' . (in_array(strtolower($order), array('random', 'rand')) ? 'RAND()' : "`t" . $field_id . "`.`".$date."` $order");
			}
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Utilities  */
		/*------------------------------------------------------------------------------------------------*/

		/**
		 * @param Entry $entry
		 */
		public function compile($entry){

			$main_lang = FLang::getMainLang();
			$entryData = $entry->getData();
			$publishField = $entryData[$this->get('publish_field')];
			$dateField = $entryData[$this->get('date_field')];
			$publishDateField = $entryData[$this->get('id')];

			// var_dump($dateField['start']['0']);die;
			// values
			foreach( FLang::getLangs() as $lc ){
				// var_dump($publishDateField);die;
				if (!isset($publishDateField['date-'.$lc]) && $publishField['value-'.$lc] == 'yes' ){
					$formattedDate = date('Y-m-d H:i:s');
					$publishDate = $formattedDate;
					if ($formattedDate < $dateField['start']['0']){
						//I'm sorry dude this article happens to be future dated; so we've got to stick to the future date
						$publishDate = $dateField['start']['0'];
					}
					$data['date-'.$lc] = $publishDate;
				}
			}

			//maybe the main should be set to the first actual value not main language value
			if (isset($data))
				$data['date'] = array_shift(array_values($data));

			//date = main language
			// if (isset($data['date-'.$main_lang]))
			// 	$data['date'] = $data['date-'.$main_lang];

			if (isset($data)){
				// If we have data Save:
				Symphony::Database()->update(
					$data,
					sprintf("tbl_entries_data_%s", $this->get('id')),
					sprintf("`entry_id` = '%s'", $entry->get('id'))
				);
			}
		}


		/*------------------------------------------------------------------------------------------------*/
		/*  Field schema  */
		/*------------------------------------------------------------------------------------------------*/

		public function appendFieldSchema($f){}

	}
