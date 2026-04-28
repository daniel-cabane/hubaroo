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
          <h1 class="text-3xl font-bold text-text-main dark:text-surface mb-3">Conditions générales d'utilisation</h1>
          <p class="text-text-muted dark:text-text-muted/70 text-sm">Dernière mise à jour : 28 avril 2026</p>
        </div>

        <div class="space-y-10">

          <!-- 1 -->
          <section>
            <h2 class="text-xl font-semibold text-text-main dark:text-surface mb-4 pb-2 border-b border-border dark:border-border/30">1. Objet et acceptation</h2>
            <p class="text-text-main dark:text-surface/80 leading-relaxed mb-3">
              Les présentes Conditions Générales d'Utilisation (ci-après « CGU ») régissent l'accès et l'utilisation de la plateforme <strong>Hubaroo</strong> (ci-après « la Plateforme »), un service en ligne destiné à l'organisation de sessions de compétition dans le cadre du Kangourou des Mathématiques.
            </p>
            <p class="text-text-main dark:text-surface/80 leading-relaxed">
              L'accès à la Plateforme implique l'acceptation pleine et entière des présentes CGU. La création d'un compte constitue une acceptation expresse de ces conditions. Les utilisateurs qui n'acceptent pas ces CGU doivent renoncer à l'utilisation de la Plateforme.
            </p>
          </section>

          <!-- 2 -->
          <section>
            <h2 class="text-xl font-semibold text-text-main dark:text-surface mb-4 pb-2 border-b border-border dark:border-border/30">2. Description du service</h2>
            <p class="text-text-main dark:text-surface/80 leading-relaxed mb-3">
              Hubaroo est une plateforme interactive dédiée au <strong>Kangourou des Mathématiques</strong>. Elle offre les fonctionnalités suivantes :
            </p>
            <ul class="space-y-2 text-text-main dark:text-surface/80 text-sm">
              <li class="flex gap-3"><span class="text-primary font-bold mt-0.5">→</span><span><strong>Consultation des épreuves</strong> : accès aux sujets des éditions 2004 à 2025 pour tous les niveaux (CE1 à Terminale), sous forme de questions illustrées.</span></li>
              <li class="flex gap-3"><span class="text-primary font-bold mt-0.5">→</span><span><strong>Sessions de compétition</strong> : création de sessions en ligne avec un code d'accès à 6 caractères, permettant à des élèves (connectés ou invités) de passer une épreuve de manière synchrone ou asynchrone, avec un chronomètre et des options de configuration (limite de temps, correction, mélange des questions, sécurité anti-triche).</span></li>
              <li class="flex gap-3"><span class="text-primary font-bold mt-0.5">→</span><span><strong>Notation automatique</strong> : les tentatives sont évaluées selon le barème officiel du Kangourou des Mathématiques (points par palier, pénalités fractionnaires, bonus de perfection).</span></li>
              <li class="flex gap-3"><span class="text-primary font-bold mt-0.5">→</span><span><strong>Gestion de classes</strong> : les enseignants peuvent créer des divisions (classes), y inviter des élèves et y associer des sessions de compétition.</span></li>
              <li class="flex gap-3"><span class="text-primary font-bold mt-0.5">→</span><span><strong>Suivi en temps réel</strong> : les réponses des élèves, les connexions et les événements de session sont transmis en temps réel via WebSockets.</span></li>
              <li class="flex gap-3"><span class="text-primary font-bold mt-0.5">→</span><span><strong>Historique</strong> : les utilisateurs connectés disposent d'un historique de leurs sessions créées et de leurs tentatives.</span></li>
            </ul>
            <p class="text-text-main dark:text-surface/80 leading-relaxed mt-4">
              Certaines fonctionnalités (création de sessions, gestion de classes, historique) nécessitent la création d'un compte. L'accès en tant qu'invité est limité à la participation à une session via un code.
            </p>
          </section>

          <!-- 3 -->
          <section>
            <h2 class="text-xl font-semibold text-text-main dark:text-surface mb-4 pb-2 border-b border-border dark:border-border/30">3. Création de compte et conditions d'accès</h2>
            <p class="text-text-main dark:text-surface/80 leading-relaxed mb-3">
              Pour créer un compte, l'utilisateur doit fournir une adresse e-mail valide et un nom complet, et choisir un rôle (Enseignant ou Élève). La création de compte est également possible via Google OAuth.
            </p>
            <p class="text-text-main dark:text-surface/80 leading-relaxed mb-3">
              L'utilisateur s'engage à fournir des informations exactes, complètes et à jour. Il est responsable de la confidentialité de ses identifiants de connexion et de toute activité réalisée depuis son compte. Toute utilisation non autorisée doit être signalée sans délai à <a href="mailto:contact@hubaroo.fr" class="text-primary hover:underline">contact@hubaroo.fr</a>.
            </p>
            <p class="text-text-main dark:text-surface/80 leading-relaxed">
              Les comptes d'élèves mineurs de moins de 15 ans doivent être créés ou supervisés par un enseignant responsable ou un représentant légal du mineur.
            </p>
          </section>

          <!-- 4 -->
          <section>
            <h2 class="text-xl font-semibold text-text-main dark:text-surface mb-4 pb-2 border-b border-border dark:border-border/30">4. Rôles et responsabilités des utilisateurs</h2>
            <div class="space-y-4">
              <div class="bg-bg dark:bg-gray-800 rounded-lg p-4">
                <h3 class="font-semibold text-text-main dark:text-surface mb-2">Enseignants</h3>
                <p class="text-text-main dark:text-surface/80 text-sm leading-relaxed">
                  Les enseignants sont responsables de la bonne utilisation des sessions qu'ils créent, des classes qu'ils administrent et des élèves qu'ils invitent. Ils s'engagent à n'utiliser la Plateforme qu'à des fins pédagogiques légitimes et à ne pas créer de sessions abusives ou à caractère illicite. Les enseignants sont responsables de la supervision des élèves mineurs placés sous leur autorité lors des sessions.
                </p>
              </div>
              <div class="bg-bg dark:bg-gray-800 rounded-lg p-4">
                <h3 class="font-semibold text-text-main dark:text-surface mb-2">Élèves</h3>
                <p class="text-text-main dark:text-surface/80 text-sm leading-relaxed">
                  Les élèves s'engagent à passer les épreuves de manière honnête, sans recourir à des aides extérieures non autorisées par l'enseignant, et à ne pas tenter de contourner les mécanismes de sécurité de la Plateforme (détection de changement d'onglet, chronomètre, etc.). Toute tentative de triche ou de manipulation du système est prohibée.
                </p>
              </div>
            </div>
          </section>

          <!-- 5 -->
          <section>
            <h2 class="text-xl font-semibold text-text-main dark:text-surface mb-4 pb-2 border-b border-border dark:border-border/30">5. Utilisations autorisées et interdites</h2>
            <p class="text-text-main dark:text-surface/80 leading-relaxed mb-3">La Plateforme est exclusivement destinée à un usage pédagogique dans le cadre du Kangourou des Mathématiques ou d'activités d'entraînement associées. Il est strictement interdit de :</p>
            <ul class="space-y-2 text-text-main dark:text-surface/80 text-sm">
              <li class="flex gap-3"><span class="text-error font-bold mt-0.5">✕</span><span>Contourner, désactiver ou tenter de compromettre les systèmes de sécurité de la Plateforme.</span></li>
              <li class="flex gap-3"><span class="text-error font-bold mt-0.5">✕</span><span>Accéder aux données d'autres utilisateurs sans autorisation.</span></li>
              <li class="flex gap-3"><span class="text-error font-bold mt-0.5">✕</span><span>Reproduire, distribuer ou exploiter commercialement les sujets d'épreuves, qui sont la propriété intellectuelle de l'Association Kangourou sans Frontières (KSF) et de ses partenaires.</span></li>
              <li class="flex gap-3"><span class="text-error font-bold mt-0.5">✕</span><span>Utiliser des robots, scripts ou tout moyen automatisé pour accéder à la Plateforme ou simuler des tentatives.</span></li>
              <li class="flex gap-3"><span class="text-error font-bold mt-0.5">✕</span><span>Créer des comptes multiples dans le but de contourner des restrictions ou de manipuler les résultats.</span></li>
              <li class="flex gap-3"><span class="text-error font-bold mt-0.5">✕</span><span>Publier, transmettre ou diffuser tout contenu illicite, offensant, diffamatoire ou portant atteinte aux droits de tiers.</span></li>
              <li class="flex gap-3"><span class="text-error font-bold mt-0.5">✕</span><span>Tenter de surcharger les serveurs ou de perturber le service (attaque par déni de service, flood, etc.).</span></li>
            </ul>
          </section>

          <!-- 6 -->
          <section>
            <h2 class="text-xl font-semibold text-text-main dark:text-surface mb-4 pb-2 border-b border-border dark:border-border/30">6. Propriété intellectuelle</h2>
            <p class="text-text-main dark:text-surface/80 leading-relaxed mb-3">
              <strong>Sujets d'épreuves :</strong> Les questions et épreuves du Kangourou des Mathématiques sont la propriété intellectuelle de l'<strong>Association Kangourou sans Frontières (KSF)</strong> et de ses licenciés nationaux. Leur reproduction, distribution ou utilisation à des fins commerciales sans autorisation explicite est interdite. La Plateforme les utilise exclusivement à des fins pédagogiques non commerciales.
            </p>
            <p class="text-text-main dark:text-surface/80 leading-relaxed mb-3">
              <strong>Code source et interface :</strong> Le code source de la Plateforme, son interface graphique, ses logos et ses textes (hors sujets d'épreuves) sont la propriété exclusive de l'éditeur de Hubaroo. Toute reproduction ou réutilisation sans autorisation écrite est interdite.
            </p>
            <p class="text-text-main dark:text-surface/80 leading-relaxed">
              <strong>Contenus utilisateurs :</strong> L'utilisateur conserve la propriété des données qu'il saisit (noms de classes, commentaires de signalement de bugs, etc.) et accorde à la Plateforme une licence non exclusive d'utilisation limitée à la fourniture du service.
            </p>
          </section>

          <!-- 7 -->
          <section>
            <h2 class="text-xl font-semibold text-text-main dark:text-surface mb-4 pb-2 border-b border-border dark:border-border/30">7. Disponibilité du service</h2>
            <p class="text-text-main dark:text-surface/80 leading-relaxed mb-3">
              Nous nous efforçons d'assurer la disponibilité de la Plateforme 24h/24 et 7j/7. Toutefois, nous nous réservons le droit d'interrompre le service temporairement pour des opérations de maintenance, de mise à jour ou en cas de circonstances imprévues, sans préavis ni responsabilité.
            </p>
            <p class="text-text-main dark:text-surface/80 leading-relaxed">
              Les sessions de compétition créées ont une durée d'expiration définie lors de leur création (par défaut 120 minutes). L'expiration automatique des sessions est une fonctionnalité du service et ne constitue pas un dysfonctionnement.
            </p>
          </section>

          <!-- 8 -->
          <section>
            <h2 class="text-xl font-semibold text-text-main dark:text-surface mb-4 pb-2 border-b border-border dark:border-border/30">8. Limitation de responsabilité</h2>
            <p class="text-text-main dark:text-surface/80 leading-relaxed mb-3">
              La Plateforme est fournie « en l'état ». Nous ne garantissons pas que le service sera exempt d'erreurs ou d'interruptions. Dans les limites permises par la loi applicable :
            </p>
            <ul class="space-y-2 text-text-main dark:text-surface/80 text-sm">
              <li class="flex gap-3"><span class="text-primary font-bold mt-0.5">→</span><span>Nous ne saurions être tenus responsables des pertes de données résultant d'une interruption de service ou d'une panne technique.</span></li>
              <li class="flex gap-3"><span class="text-primary font-bold mt-0.5">→</span><span>Nous ne saurions être tenus responsables du comportement des utilisateurs sur la Plateforme ni des litiges entre enseignants et élèves relatifs à l'organisation des sessions.</span></li>
              <li class="flex gap-3"><span class="text-primary font-bold mt-0.5">→</span><span>Nous ne garantissons pas l'exactitude des résultats de notation au-delà de l'application du barème officiel du Kangourou des Mathématiques tel que renseigné dans le système.</span></li>
              <li class="flex gap-3"><span class="text-primary font-bold mt-0.5">→</span><span>Nous ne saurions être tenus responsables des dommages indirects, pertes de chance ou préjudices consécutifs à l'utilisation ou à l'impossibilité d'utiliser la Plateforme.</span></li>
            </ul>
          </section>

          <!-- 9 -->
          <section>
            <h2 class="text-xl font-semibold text-text-main dark:text-surface mb-4 pb-2 border-b border-border dark:border-border/30">9. Suspension et résiliation du compte</h2>
            <p class="text-text-main dark:text-surface/80 leading-relaxed mb-3">
              Tout utilisateur peut demander la suppression de son compte à tout moment en contactant <a href="mailto:contact@hubaroo.fr" class="text-primary hover:underline">contact@hubaroo.fr</a>. La suppression entraîne l'effacement des données personnelles conformément à la politique de confidentialité.
            </p>
            <p class="text-text-main dark:text-surface/80 leading-relaxed">
              Nous nous réservons le droit de suspendre ou supprimer, sans préavis, tout compte dont l'utilisation viole les présentes CGU, notamment en cas de comportement frauduleux, d'atteinte à l'intégrité de la Plateforme, ou d'utilisation portant préjudice à d'autres utilisateurs.
            </p>
          </section>

          <!-- 10 -->
          <section>
            <h2 class="text-xl font-semibold text-text-main dark:text-surface mb-4 pb-2 border-b border-border dark:border-border/30">10. Droit applicable et juridiction compétente</h2>
            <p class="text-text-main dark:text-surface/80 leading-relaxed">
              Les présentes CGU sont soumises au droit français. En cas de litige relatif à leur interprétation ou à leur exécution, les parties s'efforceront de le résoudre à l'amiable. À défaut, tout litige sera soumis à la compétence exclusive des tribunaux français compétents, nonobstant la pluralité de défendeurs ou l'appel en garantie.
            </p>
          </section>

          <!-- 11 -->
          <section>
            <h2 class="text-xl font-semibold text-text-main dark:text-surface mb-4 pb-2 border-b border-border dark:border-border/30">11. Modifications des CGU</h2>
            <p class="text-text-main dark:text-surface/80 leading-relaxed">
              Nous nous réservons le droit de modifier les présentes CGU à tout moment. Les utilisateurs seront informés de toute modification substantielle par e-mail ou via un avis sur la Plateforme. La poursuite de l'utilisation du service après notification des modifications vaut acceptation des nouvelles conditions. Il appartient aux utilisateurs de consulter régulièrement la version en vigueur disponible sur cette page.
            </p>
          </section>

          <!-- 12 -->
          <section>
            <h2 class="text-xl font-semibold text-text-main dark:text-surface mb-4 pb-2 border-b border-border dark:border-border/30">12. Contact</h2>
            <p class="text-text-main dark:text-surface/80 leading-relaxed">
              Pour toute question relative aux présentes CGU, contactez-nous à : <a href="mailto:contact@hubaroo.fr" class="text-primary hover:underline">contact@hubaroo.fr</a>.
            </p>
          </section>

        </div>

        <div class="mt-12 pt-6 border-t border-border dark:border-border/30 flex gap-4 text-sm text-text-muted dark:text-text-muted/70">
          <router-link to="/terms/privacy" class="hover:text-primary transition-colors">Politique de confidentialité</router-link>
          <router-link to="/" class="hover:text-primary transition-colors">← Retour à l'accueil</router-link>
        </div>

      </div>
    </div>
  </div>
</template>

<script setup>
</script>
