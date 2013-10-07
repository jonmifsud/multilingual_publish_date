<?php

	if( !defined('__IN_SYMPHONY__') ) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');



	require_once(EXTENSIONS.'/entry_url_field/extension.driver.php');



	define_safe(MPD_NAME, 'Field: Multilingual Publish Date');
	define_safe(MPD_GROUP, 'multilingual_publish_date');



	class Extension_Multilingual_Publish_Date extends Extension
	{
		const FIELD_TABLE = 'tbl_fields_multilingual_publish_date';

		protected static $assets_loaded = false;

		protected static $fields = array();



		/*------------------------------------------------------------------------------------------------*/
		/*  Installation  */
		/*------------------------------------------------------------------------------------------------*/

		public function install(){
			return Symphony::Database()->query(sprintf(
				"CREATE TABLE `%s` (
					`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
					`field_id` INT(11) UNSIGNED NOT NULL,
					`publish_field` INT(11) DEFAULT NULL,
					`date_field` INT(11) DEFAULT NULL,
					`hide` ENUM('yes', 'no') DEFAULT 'no',
					PRIMARY KEY (`id`),
					KEY `field_id` (`field_id`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;",
				self::FIELD_TABLE
			));
		}

		public function update($prev_version){

			return true;
		}

		public function uninstall(){
			try{
				Symphony::Database()->query(sprintf(
					"DROP TABLE `%s`",
					self::FIELD_TABLE
				));
			}
			catch( DatabaseException $dbe ){
				// table deosn't exist
			}
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Delegates  */
		/*------------------------------------------------------------------------------------------------*/

		public function getSubscribedDelegates(){
			return array(
				array(
					'page'		=> '/publish/new/',
					'delegate'	=> 'EntryPostCreate',
					'callback'	=> 'compileBackendFields'
				),
				array(
					'page'		=> '/publish/edit/',
					'delegate'	=> 'EntryPostEdit',
					'callback'	=> 'compileBackendFields'
				),
				array(
					'page'		=> '/frontend/',
					'delegate'	=> 'EventPostSaveFilter',
					'callback'	=> 'compileFrontendFields'
				),

				array(
					'page' => '/system/preferences/',
					'delegate' => 'AddCustomPreferenceFieldsets',
					'callback' => 'dAddCustomPreferenceFieldsets'
				),
				array(
					'page' => '/extensions/frontend_localisation/',
					'delegate' => 'FLSavePreferences',
					'callback' => 'dFLSavePreferences'
				)
			);
		}


		//this is a nice function to auto-create older dated entries which do not have this field 
		private function autoFillPastData(){

			// 1. Get all sections which have this field
			$fields = FieldManager::fetch(null,null,'ASC','id','multilingual_publish_date');
			// var_dump($fields);die;

			foreach ($fields as $field) {
				$section = $field->get('parent_section');
				$where = 'AND mpd.entry_id IS NULL ';
				$joins = 'LEFT OUTER JOIN sym_entries_data_' . $field->get('id') . ' as mpd on e.id = mpd.entry_id ';
				// var_dump($field->get('parent_section'));die;
				// 2. Fetch all entries where this field is not set
				$entries = EntryManager::fetch(null,$section,null,0,$where,$joins);
				// var_dump($entries);die;
				foreach ($entries as $entry) {

					// 3. Find the original publish date of this entry
					$dateField = $field->get('date_field');
					$originalPublishDate = $entry->getData($dateField);
					// var_dump($originalPublishDate['start']);die;

					//this one is most likely not set so forget it
					// $publishField = $field->get('publish_field');
					// $originalPublish = $entry->getData($publishField);
					// var_dump($publishField);die;

					// 4. Iterate over the languages
					$main_lang = FLang::getMainLang();
					$langs = FLang::getLangs();
					$data = array();
					foreach( $langs as $lc ){
						$data['date-'.$lc] = $originalPublishDate['start'];
						if ($lc === $main_lang) $data['date'] = $originalPublishDate['start'];
					}
					$entry->setData($field->get('id'),$data);
					$entry->commit();
					// var_dump($entry);die;
				}
			}

		}


		/*------------------------------------------------------------------------------------------------*/
		/*  System preferences  */
		/*------------------------------------------------------------------------------------------------*/

		/**
		 * Display options on Preferences page.
		 *
		 * @param array $context
		 */
		public function dAddCustomPreferenceFieldsets($context){
			//autoFillPastData
			$this->autoFillPastData();

			$group = new XMLElement('fieldset');
			$group->setAttribute('class', 'settings');
			$group->appendChild(new XMLElement('legend', MPD_NAME));


			$label = Widget::Label(__('Consolidate entry data'));
			$label->appendChild(Widget::Input('settings['.MPD_GROUP.'][consolidate]', 'yes', 'checkbox', array('checked' => 'checked')));

			$group->appendChild($label);

			$group->appendChild(new XMLElement('p', __('Check this field if you want to consolidate database by <b>keeping</b> entry values of removed/old Language Driver language codes. Entry values of current language codes will not be affected.'), array('class' => 'help')));


			$context['wrapper']->appendChild($group);
		}

		/**
		 * Save options from Preferences page
		 *
		 * @param array $context
		 */
		public function dFLSavePreferences($context){
			$fields = Symphony::Database()->fetch(sprintf(
				'SELECT `field_id` FROM `%s`',
				self::FIELD_TABLE
			));

			if( is_array($fields) && !empty($fields) ){
				$consolidate = $context['context']['settings'][MIU_GROUP]['consolidate'];

				// Foreach field check multilanguage values foreach language
				foreach( $fields as $field ){
					$entries_table = 'tbl_entries_data_'.$field["field_id"];

					try{
						$show_columns = Symphony::Database()->fetch(sprintf(
							"SHOW COLUMNS FROM `%s` LIKE 'value-%%'",
							$entries_table
						));
					}
					catch( DatabaseException $dbe ){
						// Field doesn't exist. Better remove it's settings
						Symphony::Database()->query(sprintf(
							"DELETE FROM `%s` WHERE `field_id` = '%s';",
							self::FIELD_TABLE, $field["field_id"]
						));
						continue;
					}

					$columns = array();

					// Remove obsolete fields
					if( is_array($show_columns) && !empty($show_columns) )

						foreach( $show_columns as $column ){
							$lc = substr($column['Field'], strlen($column['Field']) - 2);

							// If not consolidate option AND column lang_code not in supported languages codes -> Drop Column
							if( ($consolidate !== 'yes') && !in_array($lc, $context['new_langs']) )
								Symphony::Database()->query(
									"ALTER TABLE `{$entries_table}`
										DROP COLUMN `date-{$lc}`;"
								);
							else
								$columns[] = $column['Field'];
						}

					// Add new fields
					foreach( $context['new_langs'] as $lc )

						if( !in_array('value-'.$lc, $columns) )
							Symphony::Database()->query(
								"ALTER TABLE `{$entries_table}`
									ADD COLUMN `date-{$lc}` datetime DEFAULT null;
							");
				}
			}
		}


		/*------------------------------------------------------------------------------------------------*/
		/*  Fields */
		/*------------------------------------------------------------------------------------------------*/

		public function registerField($field) {
			self::$fields[] = $field;
		}

		public function compileBackendFields($context) {
			foreach (self::$fields as $field) {
				$field->compile($context['entry']);
			}
		}

		public function compileFrontendFields($context) {
			foreach (self::$fields as $field) {
				$field->compile($context['entry']);
			}
		}
	}
