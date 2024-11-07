<?php

require_once(INCLUDE_DIR . 'class.signal.php');
require_once(INCLUDE_DIR . 'class.plugin.php');
require_once(INCLUDE_DIR . 'class.ticket.php');
require_once(INCLUDE_DIR . 'class.osticket.php');
require_once(INCLUDE_DIR . 'class.config.php');
require_once(INCLUDE_DIR . 'class.format.php');
require_once('config.php');

class TrellosTPlugin extends Plugin {
    var $config_class = "TrellosTPluginConfig";
    static $pluginInstance = null;

    private function getPluginInstance(?int $id) {
        if($id && ($i = $this->getInstance($id)))
            return $i;

        return $this->getInstances()->first();
    }

    function bootstrap() {
        // get plugin instance
        self::$pluginInstance = self::getPluginInstance(null);

        // Listen for ticket creation and updates
        Signal::connect('ticket.created', array($this, 'onTicketCreated'));
        Signal::connect('threadentry.created', array($this, 'onTicketUpdated'));
    }

    /**
     * Handle new ticket creation
     * 
     * @param Ticket $ticket
     */
    function onTicketCreated(Ticket $ticket) {
        error_log("Trello plugin: onTicketCreated called");
        global $cfg;
        if (!$cfg instanceof OsticketConfig) {
            error_log("Trello plugin called too early.");
            return;
        }

        // Create the Trello card
        $cardId = $this->createTrelloCard($ticket);
        
        if ($cardId) {
            // Store the Trello card ID in the ticket's custom field
            $this->saveCardIdToTicket($ticket, $cardId);
        }
    }

    /**
     * Handle ticket updates
     * 
     * @param ThreadEntry $entry
     */
    function onTicketUpdated(ThreadEntry $entry) {
        error_log("Trello plugin: onTicketUpdated called");
        global $cfg;
        if (!$cfg instanceof OsticketConfig) {
            error_log("onTicketUpdated: Trello plugin called too early.");
            return;
        }
        // Get the ticket from the ThreadEntry
        $ticket = $this->getTicket($entry);
        if (!$ticket instanceof Ticket) {
            error_log("onTicketUpdated: Ticket not found");
            return;
        }
        // Check if this is not the first message (which would be ticket creation)
        $first_entry = $ticket->getMessages()[0];
        $cardId = $this->getCardIdFromTicket($ticket);
        if ($cardId) {
            $this->updateTrelloCard($ticket, $cardId, $entry);
        } else {
            error_log("onTicketUpdated: Couldn't get the cardId");
        }
    }

    /**
     * Create a new Trello card
     * 
     * @param Ticket $ticket
     * @return string|null Card ID if successful, null if failed
     */
    private function createTrelloCard(Ticket $ticket) {
        error_log("Trello plugin: createTrelloCard called");
        $apiKey = $this->getConfigValue('trello-api-key');
        $apiToken = $this->getConfigValue('trello-api-token');
        $listId = $this->getConfigValue('trello-list-id');
        $statusColor = $this->getConfigValue('trello-label-status-color');

        if (!$apiKey || !$apiToken || !$listId) {
            error_log("Trello plugin not properly configured - missing API credentials or List ID");
            return null;
        }

        $url = "https://api.trello.com/1/cards";
        
        // Prepare card data
        $cardData = array(
            'name' => $ticket->getSubject(),
            'desc' => Format::html2text($ticket->getMessages()[0]->getBody()->getClean()),
            'start'=> $ticket->getCreateDate(),
            'idList' => $listId,
            'key' => $apiKey,
            'token' => $apiToken
        );
        // Create the card
        $response = $this->makeCurlRequest($url, $cardData, "POST");

        if ($response['statusCode'] == 200) {
            $responseData = json_decode($response['response'], true);
            $cardId = $responseData['id'] ?? null;
            $label = (string) $ticket->getUser()->getName();
            if ($cardId) {
                $this->addLabelToTrelloCard($cardId, $label, $statusColor);
            }
            return $cardId;
        }

        error_log("Failed to create Trello card. Status: {$response['statusCode']}, Response: {$response['response']}");
        return null;
    }

    /**
     * Update an existing Trello card
     * 
     * @param Ticket $ticket
     * @param string $cardId
     * @param ThreadEntry $entry
     */
    private function updateTrelloCard(Ticket $ticket, $cardId, ThreadEntry $entry) {
        error_log("Trello plugin: updateTrelloCard called");
        $apiKey = $this->getConfigValue('trello-api-key');
        $apiToken = $this->getConfigValue('trello-api-token');

        if (!$apiKey || !$apiToken) {
            error_log("Trello plugin not properly configured - missing API credentials");
            return;
        }

        $url = "https://api.trello.com/1/cards/{$cardId}/actions/comments";
        
        // Prepare update data
        $poster = $entry->getPoster();
        $date = date('d/m/Y');
        $body = Format::html2text($entry->getBody()->getClean());
        $updateData = array(
            'text' => ">**Reply from {$poster} on {$date}:**\n\n{$body}",
            'key' => $apiKey,
            'token' => $apiToken
        );

        // Update the card
        $response = $this->makeCurlRequest($url, $updateData, "POST");

        if ($response['statusCode'] != 200) {
            error_log("Failed to update Trello card. Status: {$response['statusCode']}, Response: {$response['response']}");
        }
    }

    /**
     * Save Trello card ID to ticket custom field
     * 
     * @param Ticket $ticket
     * @param string $cardId
     */
    private function saveCardIdToTicket(Ticket $ticket, $cardId) {
        $fieldId = $this->getConfigValue('trello-card-id-field');
        if (!$fieldId) {
            error_log("Trello plugin not properly configured - missing custom field ID");
            return;
        }

        $customField = $ticket->getVar($fieldId);
        if ($customField) {
            $customField->value = $cardId;
            $customField->save();
        } else {
            error_log("Trello plugin error: Custom field not found");
        }
    }

    /**
     * Get Trello card ID from ticket custom field
     * 
     * @param Ticket $ticket
     * @return string|null
     */
    private function getCardIdFromTicket(Ticket $ticket) {
        $fieldId = $this->getConfigValue('trello-card-id-field');
        if (!$fieldId) {
            error_log("Trello plugin not properly configured - missing custom field ID");
            return null;
        }

        $customField = $ticket->getVar($fieldId);
        return $customField ? $customField->value : null;
    }

    /**
     * Fetches a ticket from a ThreadEntry
     * 
     * @param ThreadEntry $entry
     * @return Ticket
     */
    function getTicket(ThreadEntry $entry) {
        error_log("Trello plugin: getTicket called");
        $ticket_id = Thread::objects()->filter([
            'id' => $entry->getThreadId()
        ])->values_flat('object_id')->first()[0];

        return Ticket::lookup(array(
            'ticket_id' => $ticket_id
        ));
    }

    /**
     * Add a label to an existing Trello card
     *
     * @param string $cardId
     * @param string $labelName
     * @param string $labelColor
     */
    private function addLabelToTrelloCard($cardId, $labelName, $labelColor) {
        error_log("Trello plugin: addLabelToTrelloCard called for {$cardId}");
        $apiKey = $this->getConfigValue('trello-api-key');
        $apiToken = $this->getConfigValue('trello-api-token');

        if (!$apiKey || !$apiToken) {
            error_log("Trello plugin not properly configured - missing API credentials");
            return;
        }

        $url = "https://api.trello.com/1/cards/{$cardId}/labels";

        // Prepare label data
        $labelData = array(
            'name' => $labelName,
            'color' => $labelColor,
            'key' => $apiKey,
            'token' => $apiToken
        );

        // Add the label
        $response = $this->makeCurlRequest($url, $labelData, "POST");

        if ($response['statusCode'] != 200) {
            error_log("Failed to add label to Trello card. Status: {$response['statusCode']}, Response: {$response['response']}");
        }
    }

    /**
     * Get configuration value by key
     * 
     * @param string $key
     * @return mixed
     */
    private function getConfigValue($key) {
        return $this->getConfig(self::$pluginInstance)->get($key);
    }

    /**
     * Make a cURL request
     * 
     * @param string $url
     * @param array $data
     * @param string $method
     * @return array
     */
    private function makeCurlRequest($url, $data, $method = "GET") {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return array('response' => $response, 'statusCode' => $statusCode);
    }
}