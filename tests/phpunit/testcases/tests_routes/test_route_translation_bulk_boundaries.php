<?php

class GP_Test_Route_Translation_Bulk_Boundaries extends GP_UnitTestCase_Route {
	public $route_class = 'GP_Route_Translation';

	public function test_bulk_fuzzy_should_not_modify_translation_from_another_set() {
		$user  = $this->factory->user->create();
		$set_a = $this->factory->translation_set->create_with_project_and_locale();
		$set_b = $this->factory->translation_set->create_with_project_and_locale();

		GP::$validator_permission->create(
			array(
				'user_id'     => $user,
				'action'      => 'approve',
				'project_id'  => $set_a->project_id,
				'locale_slug' => $set_a->locale,
				'set_slug'    => $set_a->slug,
			)
		);

		wp_set_current_user( $user );

		$translation_b = $this->factory->translation->create_with_original_for_translation_set(
			$set_b,
			array( 'status' => 'waiting' )
		);

		$_REQUEST['_gp_route_nonce'] = wp_create_nonce( 'bulk-actions' );
		$_POST['bulk']              = array(
			'action'      => 'fuzzy',
			'row-ids'     => $translation_b->original_id . '-' . $translation_b->id,
			'redirect_to' => gp_url_project( $set_a->project ),
		);

		$this->route->bulk_post( $set_a->project->path, $set_a->locale, $set_a->slug );

		$translation_b = GP::$translation->get( $translation_b->id );

		$this->assertSame(
			'waiting',
			$translation_b->status,
			'Bulk fuzzy on set A must not modify a translation that belongs to set B.'
		);
	}

	public function test_bulk_set_priority_should_not_modify_original_from_another_project() {
		$user  = $this->factory->user->create();
		$set_a = $this->factory->translation_set->create_with_project_and_locale();
		$set_b = $this->factory->translation_set->create_with_project_and_locale();

		GP::$validator_permission->create(
			array(
				'user_id'     => $user,
				'action'      => 'approve',
				'project_id'  => $set_a->project_id,
				'locale_slug' => $set_a->locale,
				'set_slug'    => $set_a->slug,
			)
		);
		GP::$permission->create(
			array(
				'user_id'     => $user,
				'action'      => 'write',
				'object_type' => 'project',
				'object_id'   => $set_a->project_id,
			)
		);

		wp_set_current_user( $user );

		$original_b = $this->factory->original->create(
			array(
				'project_id' => $set_b->project_id,
				'priority'   => 0,
			)
		);

		$_REQUEST['_gp_route_nonce'] = wp_create_nonce( 'bulk-actions' );
		$_POST['bulk']              = array(
			'action'      => 'set-priority',
			'priority'    => '1',
			'row-ids'     => $original_b->id . '-0',
			'redirect_to' => gp_url_project( $set_a->project ),
		);

		$this->route->bulk_post( $set_a->project->path, $set_a->locale, $set_a->slug );

		$original_b = GP::$original->get( $original_b->id );

		$this->assertSame(
			'0',
			(string) $original_b->priority,
			'Bulk set-priority on project A must not modify an original that belongs to project B.'
		);
	}
}

