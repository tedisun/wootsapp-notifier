# WootsApp Notifier

Plugin WordPress/WooCommerce par **Tedisun SARL** qui envoie automatiquement une notification WhatsApp au client dès que sa commande passe au statut **Terminé**.

Supporte tous les pays (numéros internationaux), un template de message entièrement personnalisable, et une intégration native avec **LicenceFlow** pour inclure les licences dans le message.

## Prérequis

- WordPress 6.0+
- WooCommerce 7.0+
- PHP 7.4+
- Un serveur [Evolution API](https://github.com/EvolutionAPI/evolution-api) avec une instance WhatsApp connectée

## Installation

1. Télécharger le ZIP depuis les [Releases GitHub](https://github.com/tedisun/wootsapp-notifier/releases)
2. Dans WordPress : **Extensions > Ajouter une extension > Téléverser une extension**
3. Uploader le ZIP et activer le plugin

## Configuration

Aller dans **WA Notify > Réglages** et renseigner :

| Champ | Description |
|---|---|
| Activer les notifications | Active/désactive les envois sans désinstaller |
| URL de l'API WhatsApp | URL de base du serveur Evolution API |
| Nom de l'instance | Nom de l'instance Evolution API |
| Clé API | Clé API (stockée de façon sécurisée) |
| Numéro de test | Numéro pour valider la config via le bouton de test |
| Intégration LicenceFlow | Inclure les licences dans le message (si LicenceFlow actif) |

## Formats de numéro acceptés

Le plugin normalise automatiquement tous les formats :

| Format saisi | Résultat |
|---|---|
| `+226 70 12 34 56` | `22670123456@s.whatsapp.net` |
| `70 12 34 56` (local BF) | `22670123456@s.whatsapp.net` |
| `+225 07 12 34 56 78` | `22507123456@s.whatsapp.net` (Côte d'Ivoire) |
| `+33 6 12 34 56 78` | `33612345678@s.whatsapp.net` (France) |

## Template de message

Allez dans **WA Notify > Template de message** pour personnaliser le contenu.

Variables disponibles :

| Variable | Description |
|---|---|
| `{prenom}` | Prénom du client |
| `{nom}` | Nom du client |
| `{prenom_nom}` | Prénom + Nom complet |
| `{numero_commande}` | Numéro de la commande |
| `{date_commande}` | Date de la commande |
| `{total}` | Montant total |
| `{monnaie}` | Symbole de la monnaie WooCommerce |
| `{produits}` | Liste des produits commandés |
| `{nb_articles}` | Nombre d'articles |
| `{licences}` | Licences LicenceFlow (si activé) |

### Exemple de template avec licences

```
✅ Commande confirmée !

Bonjour {prenom},

Merci pour votre commande !

{produits}

💰 Total : {total} FCFA

🔐 Vos licences :
{licences}

Une question ? Répondez à ce message.
```

## Intégration LicenceFlow

Si le plugin **LicenceFlow** est installé et actif :
1. Cochez "Inclure les licences dans le message WhatsApp" dans les réglages
2. Ajoutez `{licences}` dans votre template de message

Les licences sont formatées automatiquement selon leur type (clé, compte, lien, code).

## Extension via filtre WordPress

Pour ajouter des variables personnalisées depuis un autre plugin :

```php
add_filter( 'wtan_message_variables', function( $vars, $order ) {
    $vars['{ma_variable}'] = 'ma valeur';
    return $vars;
}, 10, 2 );
```

## Logs

L'historique des notifications est accessible dans **WA Notify > Logs**.

Une note privée est également ajoutée sur chaque commande (succès ou échec).

## Versioning

Ce plugin suit le versioning sémantique :
- `1.0.x` → correctif
- `1.x.0` → nouvelle fonctionnalité
- `x.0.0` → changement incompatible

## Licence

GPL-2.0-or-later — Tedisun SARL
