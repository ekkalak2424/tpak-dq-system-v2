<?php
/**
 * Unit Tests for Post Types
 * 
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

/**
 * Test Post Types Class
 */
class Test_TPAK_Post_Types extends WP_UnitTestCase {
    
    /**
     * Post Types instance
     * 
     * @var TPAK_Post_Types
     */
    private $post_types;
    
    /**
     * Set up test
     */
    public function setUp(): void {
        parent::setUp();
        $this->post_types = TPAK_Post_Types::get_instance();
    }
    
    /**
     * Test post type registration
     */
    public function test_post_type_registration() {
        // Register post types
        $this->post_types->register_post_types();
        
        // Check if post type exists
        $this->assertTrue(post_type_exists('tpak_survey_data'));
        
        // Get post type object
        $post_type = get_post_type_object('tpak_survey_data');
        
        // Test post type properties
        $this->assertFalse($post_type->public);
        $this->assertTrue($post_type->show_ui);
        $this->assertFalse($post_type->show_in_menu);
        $this->assertEquals('tpak_survey_data', $post_type->capability_type[0]);
        $this->assertTrue($post_type->map_meta_cap);
    }
    
    /**
     * Test workflow statuses
     */
    public function test_workflow_statuses() {
        $statuses = $this->post_types->get_workflow_statuses();
        
        // Check required statuses exist
        $required_statuses = array(
            'pending_a',
            'pending_b', 
            'pending_c',
            'rejected_by_b',
            'rejected_by_c',
            'finalized',
            'finalized_by_sampling'
        );
        
        foreach ($required_statuses as $status) {
            $this->assertArrayHasKey($status, $statuses);
            $this->assertNotEmpty($statuses[$status]);
        }
    }
    
    /**
     * Test meta fields definition
     */
    public function test_meta_fields() {
        $meta_fields = $this->post_types->get_meta_fields();
        
        // Check required meta fields exist
        $required_fields = array(
            '_tpak_survey_id',
            '_tpak_response_id',
            '_tpak_survey_data',
            '_tpak_workflow_status',
            '_tpak_assigned_user',
            '_tpak_audit_trail',
            '_tpak_import_date',
            '_tpak_last_modified',
            '_tpak_completion_date'
        );
        
        foreach ($required_fields as $field) {
            $this->assertArrayHasKey($field, $meta_fields);
            $this->assertArrayHasKey('type', $meta_fields[$field]);
            $this->assertArrayHasKey('description', $meta_fields[$field]);
            $this->assertArrayHasKey('single', $meta_fields[$field]);
            $this->assertArrayHasKey('default', $meta_fields[$field]);
        }
    }
    
    /**
     * Test meta field registration
     */
    public function test_meta_field_registration() {
        // Register meta fields
        $this->post_types->register_meta_fields();
        
        // Check if meta fields are registered
        $registered_meta = get_registered_meta_keys('post', 'tpak_survey_data');
        
        $this->assertArrayHasKey('_tpak_survey_id', $registered_meta);
        $this->assertArrayHasKey('_tpak_survey_data', $registered_meta);
        $this->assertArrayHasKey('_tpak_workflow_status', $registered_meta);
    }
    
    /**
     * Test meta sanitization callback
     */
    public function test_meta_sanitization() {
        // Test JSON sanitization
        $valid_json = '{"test": "value"}';
        $invalid_json = '{"test": invalid}';
        
        $sanitized_valid = $this->post_types->sanitize_meta_callback($valid_json, '_tpak_survey_data', 'post');
        $sanitized_invalid = $this->post_types->sanitize_meta_callback($invalid_json, '_tpak_survey_data', 'post');
        
        $this->assertEquals($valid_json, $sanitized_valid);
        $this->assertEquals('{}', $sanitized_invalid);
        
        // Test status sanitization
        $valid_status = 'pending_a';
        $invalid_status = 'invalid_status';
        
        $sanitized_valid_status = $this->post_types->sanitize_meta_callback($valid_status, '_tpak_workflow_status', 'post');
        $sanitized_invalid_status = $this->post_types->sanitize_meta_callback($invalid_status, '_tpak_workflow_status', 'post');
        
        $this->assertEquals($valid_status, $sanitized_valid_status);
        $this->assertEquals('pending_a', $sanitized_invalid_status);
        
        // Test user ID sanitization
        $user_id = '123';
        $sanitized_user_id = $this->post_types->sanitize_meta_callback($user_id, '_tpak_assigned_user', 'post');
        
        $this->assertEquals(123, $sanitized_user_id);
        $this->assertIsInt($sanitized_user_id);
    }
}

/**
 * Test Survey Data Model Class
 */
class Test_TPAK_Survey_Data extends WP_UnitTestCase {
    
    /**
     * Test survey data creation
     */
    public function test_survey_data_creation() {
        $survey_data = new TPAK_Survey_Data();
        
        // Set basic data
        $survey_data->set_survey_id('12345');
        $survey_data->set_response_id('67890');
        $survey_data->set_data(array('question1' => 'answer1'));
        $survey_data->set_status('pending_a');
        
        // Save to database
        $result = $survey_data->save();
        
        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);
        
        // Verify data was saved
        $this->assertEquals('12345', $survey_data->get_survey_id());
        $this->assertEquals('67890', $survey_data->get_response_id());
        $this->assertEquals(array('question1' => 'answer1'), $survey_data->get_data());
        $this->assertEquals('pending_a', $survey_data->get_status());
    }
    
    /**
     * Test survey data loading from post
     */
    public function test_survey_data_loading() {
        // Create a post manually
        $post_id = wp_insert_post(array(
            'post_type'   => 'tpak_survey_data',
            'post_title'  => 'Test Survey Data',
            'post_status' => 'publish',
        ));
        
        // Add meta data
        update_post_meta($post_id, '_tpak_survey_id', '12345');
        update_post_meta($post_id, '_tpak_response_id', '67890');
        update_post_meta($post_id, '_tpak_survey_data', '{"question1": "answer1"}');
        update_post_meta($post_id, '_tpak_workflow_status', 'pending_b');
        
        // Load survey data
        $survey_data = new TPAK_Survey_Data($post_id);
        
        // Verify loaded data
        $this->assertEquals($post_id, $survey_data->get_id());
        $this->assertEquals('12345', $survey_data->get_survey_id());
        $this->assertEquals('67890', $survey_data->get_response_id());
        $this->assertEquals(array('question1' => 'answer1'), $survey_data->get_data());
        $this->assertEquals('pending_b', $survey_data->get_status());
    }
    
    /**
     * Test audit trail functionality
     */
    public function test_audit_trail() {
        $survey_data = new TPAK_Survey_Data();
        
        // Add audit entries
        $survey_data->add_audit_entry('imported', null, null, 'Initial import');
        $survey_data->add_audit_entry('status_change', 'pending_a', 'pending_b');
        
        $audit_trail = $survey_data->get_audit_trail();
        
        $this->assertCount(2, $audit_trail);
        $this->assertEquals('imported', $audit_trail[0]['action']);
        $this->assertEquals('status_change', $audit_trail[1]['action']);
        $this->assertEquals('pending_a', $audit_trail[1]['old_value']);
        $this->assertEquals('pending_b', $audit_trail[1]['new_value']);
    }
    
    /**
     * Test status change with audit trail
     */
    public function test_status_change_with_audit() {
        $survey_data = new TPAK_Survey_Data();
        $survey_data->set_status('pending_a');
        
        // Change status
        $survey_data->set_status('pending_b');
        
        $audit_trail = $survey_data->get_audit_trail();
        
        $this->assertEquals('pending_b', $survey_data->get_status());
        $this->assertCount(1, $audit_trail);
        $this->assertEquals('status_change', $audit_trail[0]['action']);
    }
    
    /**
     * Test create from LimeSurvey
     */
    public function test_create_from_limesurvey() {
        $response_data = array(
            'question1' => 'answer1',
            'question2' => 'answer2'
        );
        
        $survey_data = TPAK_Survey_Data::create_from_limesurvey('12345', '67890', $response_data);
        
        $this->assertInstanceOf('TPAK_Survey_Data', $survey_data);
        $this->assertEquals('12345', $survey_data->get_survey_id());
        $this->assertEquals('67890', $survey_data->get_response_id());
        $this->assertEquals($response_data, $survey_data->get_data());
        $this->assertEquals('pending_a', $survey_data->get_status());
        
        // Check audit trail
        $audit_trail = $survey_data->get_audit_trail();
        $this->assertCount(1, $audit_trail);
        $this->assertEquals('imported', $audit_trail[0]['action']);
    }
    
    /**
     * Test find by response ID
     */
    public function test_find_by_response_id() {
        // Create survey data
        $survey_data = TPAK_Survey_Data::create_from_limesurvey('12345', '67890', array('test' => 'data'));
        
        // Find by response ID
        $found_data = TPAK_Survey_Data::find_by_response_id('12345', '67890');
        
        $this->assertInstanceOf('TPAK_Survey_Data', $found_data);
        $this->assertEquals($survey_data->get_id(), $found_data->get_id());
        
        // Test not found
        $not_found = TPAK_Survey_Data::find_by_response_id('99999', '99999');
        $this->assertNull($not_found);
    }
}

/**
 * Test Audit Entry Class
 */
class Test_TPAK_Audit_Entry extends WP_UnitTestCase {
    
    /**
     * Test audit entry creation
     */
    public function test_audit_entry_creation() {
        $data = array(
            'action'    => 'status_change',
            'old_value' => 'pending_a',
            'new_value' => 'pending_b',
            'notes'     => 'Test notes'
        );
        
        $entry = new TPAK_Audit_Entry($data);
        
        $this->assertEquals('status_change', $entry->get_action());
        $this->assertEquals('pending_a', $entry->get_old_value());
        $this->assertEquals('pending_b', $entry->get_new_value());
        $this->assertEquals('Test notes', $entry->get_notes());
    }
    
    /**
     * Test audit entry to array
     */
    public function test_audit_entry_to_array() {
        $entry = new TPAK_Audit_Entry(array(
            'action' => 'test_action',
            'notes'  => 'Test notes'
        ));
        
        $array = $entry->to_array();
        
        $this->assertIsArray($array);
        $this->assertArrayHasKey('timestamp', $array);
        $this->assertArrayHasKey('user_id', $array);
        $this->assertArrayHasKey('action', $array);
        $this->assertArrayHasKey('old_value', $array);
        $this->assertArrayHasKey('new_value', $array);
        $this->assertArrayHasKey('notes', $array);
        
        $this->assertEquals('test_action', $array['action']);
        $this->assertEquals('Test notes', $array['notes']);
    }
    
    /**
     * Test static factory methods
     */
    public function test_static_factory_methods() {
        // Test status change
        $status_entry = TPAK_Audit_Entry::create_status_change('pending_a', 'pending_b', 'Status updated');
        $this->assertEquals('status_change', $status_entry->get_action());
        $this->assertEquals('pending_a', $status_entry->get_old_value());
        $this->assertEquals('pending_b', $status_entry->get_new_value());
        
        // Test data edit
        $data_entry = TPAK_Audit_Entry::create_data_edit('question1', 'old_answer', 'new_answer');
        $this->assertEquals('data_edit', $data_entry->get_action());
        
        // Test user assignment
        $user_entry = TPAK_Audit_Entry::create_user_assignment(1, 2, 'User reassigned');
        $this->assertEquals('user_assignment', $user_entry->get_action());
        $this->assertEquals(1, $user_entry->get_old_value());
        $this->assertEquals(2, $user_entry->get_new_value());
    }
}