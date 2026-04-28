<template>
  <div class="min-h-screen bg-bg dark:bg-text-main py-12 px-4">
    <div class="max-w-3xl mx-auto">
      <div class="bg-surface dark:bg-gray-900 rounded-xl shadow-lg p-8 md:p-12">

        <div class="mb-10">
          <router-link to="/register" class="inline-flex items-center gap-2 text-sm text-text-muted dark:text-text-muted/70 hover:text-primary dark:hover:text-primary transition-colors mb-6">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
            Retour à l'inscription
          </router-link>
          <p class="text-sm text-text-muted dark:text-text-muted/70 uppercase tracking-wider font-medium mb-2">Hubaroo</p>
          <h1 class="text-3xl font-bold text-text-main dark:text-surface mb-3">Politique de confidentialité</h1>
          <p class="text-text-muted dark:text-text-muted/70 text-sm">Dernière mise à jour : 28 avril 2026</p>
        </div>

        <div class="prose-policy space-y-10">

          <!-- 1 -->
          <section>
            <h2 class="text-xl font-semibold text-text-main dark:text-surface mb-4 pb-2 border-b border-border dark:border-border/30">1. Responsable du traitement</h2>
            <p class="text-text-main dark:text-surface/80 leading-relaxed">
              Le responsable du traitement des données à caractère personnel collectées via la plateforme <strong>Hubaroo</strong> (ci-après « la Plateforme ») est l'entité exploitant ce service. Pour toute question relative à la protection de vos données, vous pouvez nous contacter à l'adresse suivante&nbsp;: <a href="mailto:contact@hubaroo.fr" class="text-primary hover:underline">contact@hubaroo.fr</a>.
            </p>
          </section>

          <!-- 2 -->
          <section>
            <h2 class="text-xl font-semibold text-text-main dark:text-surface mb-4 pb-2 border-b border-border dark:border-border/30">2. Données collectées</h2>
            <p class="text-text-main dark:text-surface/80 leading-relaxed mb-4">Nous collectons les catégories de données suivantes :</p>
            <div class="space-y-4">
              <div class="bg-bg dark:bg-gray-800 rounded-lg p-4">
                <h3 class="font-semibold text-text-main dark:text-surface mb-2">Données de compte</h3>
                <ul class="list-disc list-inside text-text-main dark:text-surface/80 space-y-1 text-sm">
                  <li>Nom et prénom</li>
                  <li>Adresse e-mail</li>
                  <li>Mot de passe (stocké sous forme hachée et salée, jamais en clair)</li>
                  <li>Identifiant Google (si vous vous connectez via Google OAuth)</li>
                  <li>Date et heure de vérification de l'adresse e-mail</li>
                  <li>Rôle sur la plateforme (Enseignant ou Élève)</li>
                </ul>
              </div>
              <div class="bg-bg dark:bg-gray-800 rounded-lg p-4">
                <h3 class="font-semibold text-text-main dark:text-surface mb-2">Données d'utilisation pédagogique</h3>
                <ul class="list-disc list-inside text-text-main dark:text-surface/80 space-y-1 text-sm">
                  <li>Sessions Kangourou créées (code, préférences, statut, date d'expiration)</li>
                  <li>Tentatives de compétition (réponses sélectionnées, score, horodatages, statut)</li>
                  <li>Appartenance aux classes (divisions)</li>
                  <li>Demandes de réintégration aux sessions</li>
                </ul>
              </div>
              <div class="bg-bg dark:bg-gray-800 rounded-lg p-4">
                <h3 class="font-semibold text-text-main dark:text-surface mb-2">Données techniques</h3>
                <ul class="list-disc list-inside text-text-main dark:text-surface/80 space-y-1 text-sm">
                  <li>Adresse IP (collectée par les sessions HTTP Laravel)</li>
                  <li>User-agent du navigateur</li>
                  <li>Signalements de bugs soumis par les utilisateurs connectés</li>
                </ul>
              </div>
            </div>
            <p class="text-text-muted dark:text-text-muted/70 text-sm mt-4 italic">
              Les utilisateurs non connectés (invités) peuvent créer des tentatives sans fournir aucune donnée personnelle. Un code de récupération anonyme est alors généré et stocké dans leur navigateur.
            </p>
          </section>

          <!-- 3 -->
          <section>
            <h2 class="text-xl font-semibold text-text-main dark:text-surface mb-4 pb-2 border-b border-border dark:border-border/30">3. Finalités et bases légales du traitement</h2>
            <div class="overflow-x-auto">
              <table class="w-full text-sm text-text-main dark:text-surface/80">
                <thead>
                  <tr class="border-b border-border dark:border-border/30">
                    <th class="text-left py-2 pr-4 font-semibold">Finalité</th>
                    <th class="text-left py-2 pr-4 font-semibold">Base légale (RGPD)</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-border dark:divide-border/30">
                  <tr>
                    <td class="py-2 pr-4">Création et gestion de votre compte</td>
                    <td class="py-2">Exécution d'un contrat (art. 6.1.b)</td>
                  </tr>
                  <tr>
                    <td class="py-2 pr-4">Authentification via Google OAuth</td>
                    <td class="py-2">Exécution d'un contrat (art. 6.1.b)</td>
                  </tr>
                  <tr>
                    <td class="py-2 pr-4">Enregistrement et notation des tentatives</td>
                    <td class="py-2">Exécution d'un contrat (art. 6.1.b)</td>
                  </tr>
                  <tr>
                    <td class="py-2 pr-4">Organisation des classes par les enseignants</td>
                    <td class="py-2">Exécution d'un contrat (art. 6.1.b)</td>
                  </tr>
                  <tr>
                    <td class="py-2 pr-4">Notifications et communications en temps réel</td>
                    <td class="py-2">Exécution d'un contrat (art. 6.1.b)</td>
                  </tr>
                  <tr>
                    <td class="py-2 pr-4">Réinitialisation du mot de passe</td>
                    <td class="py-2">Exécution d'un contrat (art. 6.1.b)</td>
                  </tr>
                  <tr>
                    <td class="py-2 pr-4">Sécurité, prévention des abus et signalements de bugs</td>
                    <td class="py-2">Intérêts légitimes (art. 6.1.f)</td>
                  </tr>
                  <tr>
                    <td class="py-2 pr-4">Amélioration du service</td>
                    <td class="py-2">Intérêts légitimes (art. 6.1.f)</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>

          <!-- 4 -->
          <section>
            <h2 class="text-xl font-semibold text-text-main dark:text-surface mb-4 pb-2 border-b border-border dark:border-border/30">4. Durée de conservation</h2>
            <ul class="space-y-3 text-text-main dark:text-surface/80 text-sm">
              <li class="flex gap-3">
                <span class="text-primary font-bold mt-0.5">→</span>
                <span><strong>Données de compte actif :</strong> conservées pendant toute la durée d'activité du compte, puis supprimées 3 ans après la dernière connexion ou sur demande explicite de suppression.</span>
              </li>
              <li class="flex gap-3">
                <span class="text-primary font-bold mt-0.5">→</span>
                <span><strong>Tentatives et résultats :</strong> conservés pendant la durée d'activité du compte de l'utilisateur ou de l'enseignant ayant créé la session.</span>
              </li>
              <li class="flex gap-3">
                <span class="text-primary font-bold mt-0.5">→</span>
                <span><strong>Sessions de compétition :</strong> les sessions expirées et leurs métadonnées sont conservées 1 an à des fins d'historique, puis supprimées.</span>
              </li>
              <li class="flex gap-3">
                <span class="text-primary font-bold mt-0.5">→</span>
                <span><strong>Signalements de bugs :</strong> conservés jusqu'à leur résolution, puis supprimés dans un délai maximum de 6 mois.</span>
              </li>
              <li class="flex gap-3">
                <span class="text-primary font-bold mt-0.5">→</span>
                <span><strong>Logs techniques :</strong> conservés 30 jours à des fins de sécurité et de diagnostic.</span>
              </li>
            </ul>
          </section>

          <!-- 5 -->
          <section>
            <h2 class="text-xl font-semibold text-text-main dark:text-surface mb-4 pb-2 border-b border-border dark:border-border/30">5. Destinataires et sous-traitants</h2>
            <p class="text-text-main dark:text-surface/80 leading-relaxed mb-4">
              Vos données ne sont pas vendues, louées ou cédées à des tiers à des fins commerciales. Elles peuvent toutefois être partagées avec les sous-traitants techniques suivants, dans le cadre strict de la fourniture du service :
            </p>
            <div class="space-y-3">
              <div class="bg-bg dark:bg-gray-800 rounded-lg p-4 text-sm text-text-main dark:text-surface/80">
                <strong>Google LLC</strong> — Authentification OAuth 2.0. Google reçoit votre identifiant Google et adresse e-mail lors d'une connexion via ce service. Politique de confidentialité de Google : <a href="https://policies.google.com/privacy" target="_blank" rel="noopener noreferrer" class="text-primary hover:underline">policies.google.com/privacy</a>.
              </div>
              <div class="bg-bg dark:bg-gray-800 rounded-lg p-4 text-sm text-text-main dark:text-surface/80">
                <strong>Hébergeur de l'infrastructure</strong> — Le serveur hébergeant la plateforme et la base de données. Les données sont stockées dans l'Union européenne.
              </div>
            </div>
          </section>

          <!-- 6 -->
          <section>
            <h2 class="text-xl font-semibold text-text-main dark:text-surface mb-4 pb-2 border-b border-border dark:border-border/30">6. Transferts hors Union européenne</h2>
            <p class="text-text-main dark:text-surface/80 leading-relaxed">
              Dans le cadre de l'authentification Google OAuth, vos données peuvent transiter vers des serveurs de Google LLC situés aux États-Unis. Ce transfert est encadré par les Clauses Contractuelles Types (CCT) approuvées par la Commission européenne et par le cadre Data Privacy Framework EU-États-Unis. Aucun autre transfert vers des pays tiers n'est effectué.
            </p>
          </section>

          <!-- 7 -->
          <section>
            <h2 class="text-xl font-semibold text-text-main dark:text-surface mb-4 pb-2 border-b border-border dark:border-border/30">7. Vos droits</h2>
            <p class="text-text-main dark:text-surface/80 leading-relaxed mb-4">
              Conformément au Règlement Général sur la Protection des Données (RGPD) et à la loi Informatique et Libertés modifiée, vous disposez des droits suivants concernant vos données personnelles :
            </p>
            <ul class="space-y-2 text-text-main dark:text-surface/80 text-sm">
              <li class="flex gap-3"><span class="text-primary font-bold mt-0.5">→</span><span><strong>Droit d'accès</strong> : obtenir la confirmation du traitement et une copie de vos données.</span></li>
              <li class="flex gap-3"><span class="text-primary font-bold mt-0.5">→</span><span><strong>Droit de rectification</strong> : corriger des données inexactes ou incomplètes.</span></li>
              <li class="flex gap-3"><span class="text-primary font-bold mt-0.5">→</span><span><strong>Droit à l'effacement</strong> (« droit à l'oubli ») : demander la suppression de vos données, sous réserve des obligations légales de conservation.</span></li>
              <li class="flex gap-3"><span class="text-primary font-bold mt-0.5">→</span><span><strong>Droit à la limitation</strong> : restreindre le traitement de vos données dans certains cas prévus par le RGPD.</span></li>
              <li class="flex gap-3"><span class="text-primary font-bold mt-0.5">→</span><span><strong>Droit à la portabilité</strong> : recevoir vos données dans un format structuré et lisible par machine.</span></li>
              <li class="flex gap-3"><span class="text-primary font-bold mt-0.5">→</span><span><strong>Droit d'opposition</strong> : vous opposer au traitement fondé sur l'intérêt légitime.</span></li>
              <li class="flex gap-3"><span class="text-primary font-bold mt-0.5">→</span><span><strong>Droit de retrait du consentement</strong> : retirer à tout moment un consentement préalablement accordé.</span></li>
            </ul>
            <p class="text-text-main dark:text-surface/80 leading-relaxed mt-4">
              Pour exercer ces droits, adressez votre demande par e-mail à <a href="mailto:contact@hubaroo.fr" class="text-primary hover:underline">contact@hubaroo.fr</a> en joignant une copie d'un justificatif d'identité. Nous nous engageons à vous répondre dans un délai d'un mois. En cas de réponse insatisfaisante, vous disposez du droit d'introduire une réclamation auprès de la <strong>CNIL</strong> (<a href="https://www.cnil.fr" target="_blank" rel="noopener noreferrer" class="text-primary hover:underline">www.cnil.fr</a>).
            </p>
          </section>

          <!-- 8 -->
          <section>
            <h2 class="text-xl font-semibold text-text-main dark:text-surface mb-4 pb-2 border-b border-border dark:border-border/30">8. Données relatives aux mineurs</h2>
            <p class="text-text-main dark:text-surface/80 leading-relaxed">
              La Plateforme est susceptible d'être utilisée par des élèves mineurs dans le cadre scolaire, sous la supervision de leur enseignant. Les comptes d'élèves mineurs de moins de 15 ans doivent être créés ou validés par un enseignant responsable, conformément à l'article 8 du RGPD et à l'article 45 de la loi Informatique et Libertés. Nous nous engageons à ne pas collecter de données non nécessaires auprès des mineurs et à ne leur envoyer aucune communication commerciale.
            </p>
          </section>

          <!-- 9 -->
          <section>
            <h2 class="text-xl font-semibold text-text-main dark:text-surface mb-4 pb-2 border-b border-border dark:border-border/30">9. Sécurité des données</h2>
            <p class="text-text-main dark:text-surface/80 leading-relaxed">
              Nous mettons en œuvre des mesures techniques et organisationnelles appropriées pour protéger vos données contre tout accès non autorisé, perte, altération ou divulgation. Ces mesures comprennent notamment : le chiffrement des mots de passe (hachage bcrypt), le transport des données via HTTPS, le cloisonnement des accès à la base de données, et une gestion des rôles limitant l'accès aux données aux seules personnes habilitées. En cas de violation de données à caractère personnel susceptible d'engendrer un risque pour vos droits et libertés, nous vous en informerons dans les meilleurs délais conformément à l'article 34 du RGPD.
            </p>
          </section>

          <!-- 10 -->
          <section>
            <h2 class="text-xl font-semibold text-text-main dark:text-surface mb-4 pb-2 border-b border-border dark:border-border/30">10. Cookies et stockage local</h2>
            <p class="text-text-main dark:text-surface/80 leading-relaxed mb-3">
              La Plateforme utilise les mécanismes suivants :
            </p>
            <ul class="space-y-2 text-text-main dark:text-surface/80 text-sm">
              <li class="flex gap-3"><span class="text-primary font-bold mt-0.5">→</span><span><strong>Cookie de session</strong> : cookie strictement nécessaire au maintien de votre authentification, expirant à la fermeture du navigateur ou après 120 minutes d'inactivité. Aucun consentement n'est requis (art. 82 de la loi Informatique et Libertés).</span></li>
              <li class="flex gap-3"><span class="text-primary font-bold mt-0.5">→</span><span><strong>Stockage local (localStorage)</strong> : utilisé pour mémoriser temporairement l'identifiant de tentative en cours des utilisateurs non connectés afin de permettre la reprise d'une session interrompue. Ces données restent sur votre appareil.</span></li>
            </ul>
            <p class="text-text-main dark:text-surface/80 text-sm mt-3">
              Nous n'utilisons aucun cookie publicitaire, analytique tiers ou de traçage comportemental.
            </p>
          </section>

          <!-- 11 -->
          <section>
            <h2 class="text-xl font-semibold text-text-main dark:text-surface mb-4 pb-2 border-b border-border dark:border-border/30">11. Modifications de la présente politique</h2>
            <p class="text-text-main dark:text-surface/80 leading-relaxed">
              Nous nous réservons le droit de modifier la présente politique de confidentialité à tout moment afin de refléter les évolutions légales, réglementaires ou fonctionnelles de la Plateforme. Toute modification substantielle vous sera notifiée par e-mail ou via un bandeau d'information sur la Plateforme. La version en vigueur est celle affichée sur cette page avec sa date de mise à jour.
            </p>
          </section>

          <!-- 12 -->
          <section>
            <h2 class="text-xl font-semibold text-text-main dark:text-surface mb-4 pb-2 border-b border-border dark:border-border/30">12. Contact</h2>
            <p class="text-text-main dark:text-surface/80 leading-relaxed">
              Pour toute question relative au traitement de vos données personnelles ou pour exercer vos droits, contactez-nous à : <a href="mailto:contact@hubaroo.fr" class="text-primary hover:underline">contact@hubaroo.fr</a>.
            </p>
          </section>

        </div>

        <div class="mt-12 pt-6 border-t border-border dark:border-border/30 flex gap-4 text-sm text-text-muted dark:text-text-muted/70">
          <router-link to="/terms/service" class="hover:text-primary transition-colors">Conditions d'utilisation</router-link>
          <router-link to="/" class="hover:text-primary transition-colors">← Retour à l'accueil</router-link>
        </div>

      </div>
    </div>
  </div>
</template>

<script setup>
</script>
