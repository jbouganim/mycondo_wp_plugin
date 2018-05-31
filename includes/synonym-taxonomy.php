<?php

	class MyCondo_Synonym_Tax {

		const SLUG = "mood_synonyms";

		static function setup(){
			add_action( 'init', array(__CLASS__, 'register_tax'), 11 );
		}

		static function register_tax() {
			register_taxonomy(self::SLUG, array( MyCondo_Routine_Post_Type::SLUG, MyCondo_Mood_Post_Type::SLUG ), array(
			    'hierarchical' => false, 
			    'label' => __("Synonyms"), 
			    'singular_name' => __("Synonym"), 
			    'rewrite' => false, 
			    //'public' => false,
			    'query_var' => true
			    )
			);
		}
	}

