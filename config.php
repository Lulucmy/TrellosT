<?php

require_once INCLUDE_DIR . 'class.plugin.php';

class TrellosTPluginConfig extends PluginConfig {
    static function translate() {
        if (!method_exists('Plugin', 'translate')) {
            return array(
                function($x) { return $x; },
                function($x, $y, $n) { return $n != 1 ? $y : $x; },
            );
        }
        return Plugin::translate('trello');
    }

    function getOptions() {
        list($__, $_N) = self::translate();
        return array(
            'trello' => new SectionBreakField(array(
                'label' => 'Trello Integration',
            )),
            'trello-api-key' => new TextboxField(array(
                'label' => 'Trello API Key',
                'configuration' => array('size' => 60, 'length' => 100),
            )),
            'trello-api-token' => new TextboxField(array(
                'label' => 'Trello API Token',
                'configuration' => array('size' => 60, 'length' => 100),
            )),
            'trello-list-id' => new TextboxField(array(
                'label' => 'Trello List ID',
                'configuration' => array('size' => 60, 'length' => 100),
            )),
            'trello-card-id-field' => new TextboxField(array(
                'label' => 'Trello Card ID Custom Field',
                'hint' => 'Enter the ID of the custom field created to store the Trello Card ID',
                'configuration' => array('size' => 60, 'length' => 100),
            )),
            'trello-label-status-color' => new TextboxField(array(
                'label' => 'Trello Label Status Color',
                'hint' => 'Enter the color of the label displaying the status on the card (red/green/blue/black/white)',
                'configuration' => array('size' => 60, 'length' => 100),
            )),
        );
    }
}