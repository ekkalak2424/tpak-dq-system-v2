<?php
/**
 * Workflow Diagram Generator
 * 
 * Generates visual representations of the workflow
 * 
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * TPAK Workflow Diagram Class
 */
class TPAK_Workflow_Diagram {
    
    /**
     * Generate Mermaid diagram
     * 
     * @return string
     */
    public static function generate_mermaid_diagram() {
        $workflow = TPAK_Workflow::get_instance();
        $states = $workflow->get_workflow_states();
        $transitions = $workflow->get_workflow_transitions();
        
        $diagram = "stateDiagram-v2\n";
        $diagram .= "    [*] --> pending_a : Import\n";
        
        // Add transitions
        foreach ($transitions as $action => $transition) {
            $from_states = $transition['from'];
            $to_state = $transition['to'];
            
            // Handle sampling (special case with multiple outcomes)
            if (isset($transition['is_sampling']) && $transition['is_sampling']) {
                foreach ($from_states as $from) {
                    $diagram .= "    $from --> finalized_by_sampling : Sampling (70%)\n";
                    $diagram .= "    $from --> pending_c : Sampling (30%)\n";
                }
            } else {
                foreach ($from_states as $from) {
                    $label = $transition['label'];
                    $diagram .= "    $from --> $to_state : $label\n";
                }
            }
        }
        
        // Add final states
        $diagram .= "    finalized --> [*]\n";
        $diagram .= "    finalized_by_sampling --> [*]\n";
        
        return $diagram;
    }
    
    /**
     * Generate HTML workflow diagram
     * 
     * @return string
     */
    public static function generate_html_diagram() {
        $workflow = TPAK_Workflow::get_instance();
        $states = $workflow->get_workflow_states();
        $transitions = $workflow->get_workflow_transitions();
        
        ob_start();
        ?>
        <div class="tpak-workflow-diagram">
            <style>
            .tpak-workflow-diagram {
                font-family: Arial, sans-serif;
                max-width: 1200px;
                margin: 20px auto;
            }
            .workflow-states {
                display: flex;
                flex-wrap: wrap;
                gap: 20px;
                justify-content: center;
                margin-bottom: 30px;
            }
            .workflow-state {
                border: 2px solid #ddd;
                border-radius: 8px;
                padding: 15px;
                min-width: 200px;
                text-align: center;
                position: relative;
            }
            .workflow-state.final {
                border-color: #38a169;
                background-color: #f0fff4;
            }
            .workflow-state.pending {
                border-color: #ed8936;
                background-color: #fffaf0;
            }
            .workflow-state.rejected {
                border-color: #e53e3e;
                background-color: #fff5f5;
            }
            .state-title {
                font-weight: bold;
                font-size: 14px;
                margin-bottom: 8px;
            }
            .state-description {
                font-size: 12px;
                color: #666;
                margin-bottom: 10px;
            }
            .state-roles {
                font-size: 11px;
                color: #888;
            }
            .workflow-transitions {
                margin-top: 20px;
            }
            .transition-list {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 15px;
            }
            .transition-item {
                border: 1px solid #ddd;
                border-radius: 4px;
                padding: 10px;
                background: #f9f9f9;
            }
            .transition-title {
                font-weight: bold;
                color: #2d3748;
                margin-bottom: 5px;
            }
            .transition-details {
                font-size: 12px;
                color: #666;
            }
            </style>
            
            <h3><?php esc_html_e('TPAK DQ System Workflow States', 'tpak-dq-system'); ?></h3>
            
            <div class="workflow-states">
                <?php foreach ($states as $state_key => $state): ?>
                    <?php 
                    $class = 'workflow-state';
                    if (isset($state['is_final']) && $state['is_final']) {
                        $class .= ' final';
                    } elseif (strpos($state_key, 'rejected') !== false) {
                        $class .= ' rejected';
                    } else {
                        $class .= ' pending';
                    }
                    ?>
                    <div class="<?php echo esc_attr($class); ?>" style="border-color: <?php echo esc_attr($state['color']); ?>;">
                        <div class="state-title"><?php echo esc_html($state['label']); ?></div>
                        <div class="state-description"><?php echo esc_html($state['description']); ?></div>
                        <?php if (!empty($state['allowed_roles'])): ?>
                            <div class="state-roles">
                                <strong><?php esc_html_e('Roles:', 'tpak-dq-system'); ?></strong>
                                <?php 
                                $role_labels = array();
                                foreach ($state['allowed_roles'] as $role) {
                                    switch ($role) {
                                        case 'tpak_interviewer_a':
                                            $role_labels[] = __('Interviewer A', 'tpak-dq-system');
                                            break;
                                        case 'tpak_supervisor_b':
                                            $role_labels[] = __('Supervisor B', 'tpak-dq-system');
                                            break;
                                        case 'tpak_examiner_c':
                                            $role_labels[] = __('Examiner C', 'tpak-dq-system');
                                            break;
                                        case 'administrator':
                                            $role_labels[] = __('Administrator', 'tpak-dq-system');
                                            break;
                                    }
                                }
                                echo esc_html(implode(', ', $role_labels));
                                ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="workflow-transitions">
                <h4><?php esc_html_e('Available Transitions', 'tpak-dq-system'); ?></h4>
                <div class="transition-list">
                    <?php foreach ($transitions as $action_key => $transition): ?>
                        <div class="transition-item">
                            <div class="transition-title"><?php echo esc_html($transition['label']); ?></div>
                            <div class="transition-details">
                                <strong><?php esc_html_e('From:', 'tpak-dq-system'); ?></strong> 
                                <?php 
                                $from_labels = array();
                                foreach ($transition['from'] as $from_state) {
                                    $from_labels[] = $states[$from_state]['label'] ?? $from_state;
                                }
                                echo esc_html(implode(', ', $from_labels));
                                ?><br>
                                
                                <strong><?php esc_html_e('To:', 'tpak-dq-system'); ?></strong> 
                                <?php 
                                if (is_array($transition['to'])) {
                                    echo esc_html__('Sampling Gate (70% Finalized, 30% to Examiner)', 'tpak-dq-system');
                                } else {
                                    echo esc_html($states[$transition['to']]['label'] ?? $transition['to']);
                                }
                                ?><br>
                                
                                <strong><?php esc_html_e('Required Role:', 'tpak-dq-system'); ?></strong> 
                                <?php 
                                switch ($transition['required_role']) {
                                    case 'tpak_interviewer_a':
                                        echo esc_html__('Interviewer A', 'tpak-dq-system');
                                        break;
                                    case 'tpak_supervisor_b':
                                        echo esc_html__('Supervisor B', 'tpak-dq-system');
                                        break;
                                    case 'tpak_examiner_c':
                                        echo esc_html__('Examiner C', 'tpak-dq-system');
                                        break;
                                    default:
                                        echo esc_html($transition['required_role']);
                                }
                                ?>
                                
                                <?php if (isset($transition['requires_note']) && $transition['requires_note']): ?>
                                    <br><em><?php esc_html_e('Requires note/reason', 'tpak-dq-system'); ?></em>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Generate workflow statistics chart
     * 
     * @param array $statistics
     * @return string
     */
    public static function generate_statistics_chart($statistics) {
        if (empty($statistics)) {
            return '<p>' . __('No statistics available.', 'tpak-dq-system') . '</p>';
        }
        
        $total = array_sum(array_column($statistics, 'count'));
        
        ob_start();
        ?>
        <div class="tpak-workflow-statistics">
            <style>
            .tpak-workflow-statistics {
                font-family: Arial, sans-serif;
                max-width: 800px;
                margin: 20px auto;
            }
            .stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 15px;
                margin-bottom: 20px;
            }
            .stat-card {
                border: 1px solid #ddd;
                border-radius: 8px;
                padding: 15px;
                text-align: center;
                background: #fff;
            }
            .stat-number {
                font-size: 24px;
                font-weight: bold;
                margin-bottom: 5px;
            }
            .stat-label {
                font-size: 12px;
                color: #666;
                margin-bottom: 10px;
            }
            .stat-bar {
                height: 8px;
                background: #f0f0f0;
                border-radius: 4px;
                overflow: hidden;
            }
            .stat-fill {
                height: 100%;
                transition: width 0.3s ease;
            }
            .stat-percentage {
                font-size: 11px;
                color: #888;
                margin-top: 5px;
            }
            </style>
            
            <h4><?php esc_html_e('Workflow Statistics', 'tpak-dq-system'); ?></h4>
            
            <div class="stats-grid">
                <?php foreach ($statistics as $status => $data): ?>
                    <?php 
                    $percentage = $total > 0 ? round(($data['count'] / $total) * 100, 1) : 0;
                    ?>
                    <div class="stat-card">
                        <div class="stat-number" style="color: <?php echo esc_attr($data['color']); ?>;">
                            <?php echo esc_html($data['count']); ?>
                        </div>
                        <div class="stat-label"><?php echo esc_html($data['label']); ?></div>
                        <div class="stat-bar">
                            <div class="stat-fill" style="width: <?php echo esc_attr($percentage); ?>%; background-color: <?php echo esc_attr($data['color']); ?>;"></div>
                        </div>
                        <div class="stat-percentage"><?php echo esc_html($percentage); ?>%</div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div style="text-align: center; color: #666; font-size: 14px;">
                <strong><?php esc_html_e('Total Records:', 'tpak-dq-system'); ?></strong> <?php echo esc_html($total); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}