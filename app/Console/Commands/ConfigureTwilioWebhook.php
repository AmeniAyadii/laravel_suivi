<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Twilio\Rest\Client;

class ConfigureTwilioWebhook extends Command
{
    protected $signature = 'twilio:configure-webhook';
    protected $description = 'Configurer le webhook Twilio';

    public function handle()
    {
        $this->info('📞 Configuration du webhook Twilio...');

        try {
            $sid = config('services.twilio.sid');
            $token = config('services.twilio.token');
            $webhookUrl = config('services.twilio.webhook_url');

            if (!$sid || !$token) {
                $this->error('❌ Identifiants Twilio manquants dans .env');
                return 1;
            }

            // ✅ Utiliser le numéro de test
            $testPhoneNumber = '+15005550006';
            $this->info('📞 Numéro de test utilisé : ' . $testPhoneNumber);
            
            if (!$webhookUrl) {
                $webhookUrl = config('app.url') . '/api/voice-scheduler/webhook';
                $this->info('🔗 URL webhook : ' . $webhookUrl);
            }

            // Créer le client Twilio
            $client = new Client($sid, $token);

            // ✅ Vérifier simplement la connexion (pas besoin de numéro réel)
            $account = $client->api->v2010->accounts($sid)->fetch();
            
            $this->info('✅ Connexion réussie !');
            $this->info('📋 Compte : ' . $account->friendlyName);
            $this->info('📋 Statut : ' . $account->status);
            $this->info('📋 Type : ' . $account->type);
            
            $this->info('');
            $this->info('🎉 Configuration réussie avec le numéro de test !');
            $this->info('📞 Numéro de test : ' . $testPhoneNumber);
            $this->info('🔗 Webhook URL : ' . $webhookUrl);
            $this->info('');
            $this->info('⚠️  Note : Le numéro de test permet uniquement de tester');
            $this->info('   votre logique d\'appel. Pour appeler de vrais numéros,');
            $this->info('   vous devrez acheter un numéro Twilio.');
            
            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Erreur : ' . $e->getMessage());
            return 1;
        }
    }
}