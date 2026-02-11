<?php
/**
 * SMS Suite - Gateway Registry
 *
 * Manages gateway driver registration and instantiation
 */

namespace SMSSuite\Gateways;

use WHMCS\Database\Capsule;
use Exception;

class GatewayRegistry
{
    /**
     * Registered gateway drivers
     * @var array
     */
    private static array $drivers = [];

    /**
     * Instantiated gateways cache
     * @var array
     */
    private static array $instances = [];

    /**
     * Register a gateway driver
     *
     * @param string $type Gateway type identifier
     * @param string $class Fully qualified class name
     */
    public static function register(string $type, string $class): void
    {
        self::$drivers[$type] = $class;
    }

    /**
     * Get all registered driver types
     *
     * @return array
     */
    public static function getRegisteredTypes(): array
    {
        return array_keys(self::$drivers);
    }

    /**
     * Get all registered drivers with metadata
     *
     * @return array
     */
    public static function getAvailableDrivers(): array
    {
        $drivers = [];

        foreach (self::$drivers as $type => $class) {
            try {
                $instance = new $class();
                $drivers[$type] = [
                    'type' => $type,
                    'name' => $instance->getName(),
                    'channels' => $instance->getSupportedChannels(),
                    'fields' => $instance->getRequiredFields(),
                ];
            } catch (Exception $e) {
                // Skip invalid drivers
            }
        }

        return $drivers;
    }

    /**
     * Check if a driver type is registered
     *
     * @param string $type
     * @return bool
     */
    public static function hasDriver(string $type): bool
    {
        return isset(self::$drivers[$type]);
    }

    /**
     * Create a gateway instance by type
     *
     * @param string $type
     * @param array $config
     * @return GatewayInterface
     * @throws Exception
     */
    public static function create(string $type, array $config = []): GatewayInterface
    {
        if (!isset(self::$drivers[$type])) {
            throw new Exception("Gateway driver not found: {$type}");
        }

        $class = self::$drivers[$type];
        $gateway = new $class();
        $gateway->setConfig($config);

        return $gateway;
    }

    /**
     * Get gateway instance from database record
     *
     * @param int $gatewayId
     * @return GatewayInterface
     * @throws Exception
     */
    public static function getById(int $gatewayId): GatewayInterface
    {
        // Check cache
        if (isset(self::$instances[$gatewayId])) {
            return self::$instances[$gatewayId];
        }

        // Load from database
        $record = Capsule::table('mod_sms_gateways')
            ->where('id', $gatewayId)
            ->first();

        if (!$record) {
            throw new Exception("Gateway not found: {$gatewayId}");
        }

        if (!$record->status) {
            throw new Exception("Gateway is disabled: {$gatewayId}");
        }

        // Decrypt credentials
        $credentials = [];
        if (!empty($record->credentials)) {
            $decrypted = \sms_suite_decrypt($record->credentials);
            $credentials = json_decode($decrypted, true) ?: [];
        }

        // Parse settings
        $settings = [];
        if (!empty($record->settings)) {
            $settings = json_decode($record->settings, true) ?: [];
        }

        // Merge config
        $config = array_merge($credentials, $settings, [
            'gateway_id' => $gatewayId,
            'gateway_name' => $record->name,
            'webhook_token' => $record->webhook_token,
        ]);

        // Create instance
        $gateway = self::create($record->type, $config);

        // Cache
        self::$instances[$gatewayId] = $gateway;

        return $gateway;
    }

    /**
     * Clear instance cache
     *
     * @param int|null $gatewayId
     */
    public static function clearCache(?int $gatewayId = null): void
    {
        if ($gatewayId !== null) {
            unset(self::$instances[$gatewayId]);
        } else {
            self::$instances = [];
        }
    }

    /**
     * Initialize built-in drivers
     */
    public static function init(): void
    {
        // Load additional gateway drivers
        require_once __DIR__ . '/GatewayDrivers.php';
        require_once __DIR__ . '/AirtouchGateway.php';

        // Register core gateway drivers
        self::register('generic_http', GenericHttpGateway::class);
        self::register('twilio', TwilioGateway::class);
        self::register('plivo', PlivoGateway::class);
        self::register('vonage', VonageGateway::class);
        self::register('infobip', InfobipGateway::class);

        // Register Airtouch Kenya gateway
        self::register('airtouch', AirtouchGateway::class);

        // Register extended gateway drivers from GatewayDrivers.php
        self::register('messagebird', MessageBirdGateway::class);
        self::register('clickatell', ClickatellGateway::class);
        self::register('sinch', SinchGateway::class);
        self::register('bandwidth', BandwidthGateway::class);
        self::register('africastalking', AfricasTalkingGateway::class);
        self::register('termii', TermiiGateway::class);
        self::register('msg91', Msg91Gateway::class);
        self::register('smsglobal', SmsGlobalGateway::class);
        self::register('telnyx', TelnyxGateway::class);
        self::register('telesign', TelesignGateway::class);
        self::register('aws_sns', AwsSnsGateway::class);
        self::register('routee', RouteeGateway::class);
        self::register('bulksms', BulkSmsGateway::class);
        self::register('smsto', SmsToGateway::class);
        self::register('signalwire', SignalWireGateway::class);
        self::register('zenvia', ZenviaGateway::class);
        self::register('meta_whatsapp', MetaWhatsAppGateway::class);

        // Allow extensions via hook
        if (function_exists('run_hook')) {
            $customDrivers = run_hook('SMSSuiteRegisterGateways', []);
            if (is_array($customDrivers)) {
                foreach ($customDrivers as $type => $class) {
                    self::register($type, $class);
                }
            }
        }
    }

    /**
     * Get all available gateway types with metadata
     * Includes types from GatewayTypes registry that may not have full implementations
     *
     * @return array
     */
    public static function getAllGatewayTypes(): array
    {
        $types = [];

        // First get fully implemented drivers
        foreach (self::$drivers as $type => $class) {
            try {
                $instance = new $class();
                $types[$type] = [
                    'type' => $type,
                    'name' => $instance->getName(),
                    'channels' => $instance->getSupportedChannels(),
                    'fields' => $instance->getRequiredFields(),
                    'implemented' => true,
                ];
            } catch (Exception $e) {
                // Skip invalid drivers
            }
        }

        // Add all types from GatewayTypes for display purposes
        foreach (GatewayTypes::TYPES as $type => $info) {
            if (!isset($types[$type])) {
                $types[$type] = [
                    'type' => $type,
                    'name' => $info['name'],
                    'category' => $info['category'] ?? 'Other',
                    'channels' => ['sms'],
                    'fields' => GatewayTypes::getFields($type),
                    'implemented' => false,
                ];
            } else {
                $types[$type]['category'] = $info['category'] ?? 'Other';
            }
        }

        return $types;
    }

    /**
     * Get gateway types by category
     *
     * @param string $category
     * @return array
     */
    public static function getTypesByCategory(string $category): array
    {
        $all = self::getAllGatewayTypes();
        return array_filter($all, function ($type) use ($category) {
            return ($type['category'] ?? 'Other') === $category;
        });
    }

    /**
     * Get all categories
     *
     * @return array
     */
    public static function getCategories(): array
    {
        return GatewayTypes::getCategories();
    }
}

// Initialize on load
GatewayRegistry::init();
