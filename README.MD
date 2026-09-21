## Start le serveur en local

```
symfony server:start --allow-all-ip --p12=cert.p12
```

## Changement d'IP locale (Wi-Fi qui change, nouvelle box, etc.)

Deux certificats référencent l'IP locale en dur, avec deux CA différentes.
Installer/faire confiance à la mauvaise CA sur le téléphone donne la même
erreur (`unknown certificate` / `ERR_CERT_AUTHORITY_INVALID`) dans les deux
cas, donc bien traiter les deux.

### 1. Cert du serveur Symfony (`cert.p12`, via mkcert) — sert `https://<ip>:8100`

Régénérer avec la nouvelle IP :
```bash
mkcert -pkcs12 -p12-file cert.p12 localhost 127.0.0.1 <nouvelle-ip>
```
`mkcert` fixe le mot de passe du p12 à `changeit`, mais `symfony server:start`
n'a pas d'option pour le lui fournir et attend un p12 sans mot de passe.
Re-exporter sans passphrase :
```bash
openssl pkcs12 -in cert.p12 -out /tmp/cert-temp.pem -nodes -passin pass:changeit
openssl pkcs12 -export -in /tmp/cert-temp.pem -out cert.p12 -passout pass:
rm /tmp/cert-temp.pem
```
Puis relancer `symfony server:start --allow-all-ip --p12=cert.p12`.

La CA mkcert (`~/Library/Application Support/mkcert/rootCA.pem`) ne change
pas avec l'IP. Si elle est déjà installée sur le téléphone, pas besoin de la
réinstaller — seul le cert change. Sinon (première fois) :
```bash
adb push "$HOME/Library/Application Support/mkcert/rootCA.pem" /sdcard/Download/mkcert-root-ca.crt
```
Puis sur le téléphone : Paramètres > Sécurité > Chiffrement et identifiants >
Installer un certificat > Certificat CA > `mkcert-root-ca.crt`.

### 2. Cert du hub Mercure (Caddy) — sert `https://<ip>:5050`

Contrairement au cert Symfony, celui-ci n'a pas besoin d'être régénéré à la
main : Caddy le fait automatiquement au démarrage pour chaque nom listé dans
`SERVER_NAME`. Il faut juste mettre à jour la config avec la nouvelle IP.

1. Dans `compose.yaml`, service `mercure_hub`, remplacer l'IP dans
   `GLOBAL_OPTIONS` (`default_sni`), `SERVER_NAME` et
   `MERCURE_EXTRA_DIRECTIVES` (`cors_origins`).
2. Dans `.env.local`, mettre à jour `MERCURE_PUBLIC_URL` avec la nouvelle IP
   (c'est cette valeur que Symfony renvoie au client pour se connecter au
   hub — l'oublier fait échouer la connexion même si `compose.yaml` est bon).
3. Recréer le conteneur :
   ```bash
   docker compose up -d --force-recreate mercure_hub
   ```

La CA Caddy (stockée dans le volume `mercure_data`) ne change pas non plus
tant que le volume n'est pas supprimé — pas besoin de retoucher la CA
installée sur les téléphones pour un simple changement d'IP. Voir la section
suivante si le volume a été supprimé (`docker compose down -v`) et qu'il
faut regénérer cette CA.

### 3. Ne pas oublier une IP qui traîne

```bash
grep -rn "192\.168\." --include=*.yaml --include=*.env* --exclude-dir=vendor .
```
à lancer à la racine pour traquer une IP oubliée (`compose.yaml`,
`.env.local`...).

## Mercure en local (HTTPS + certif auto-signé)

Le hub Mercure (Caddy) génère sa propre CA locale, stockée dans le volume
Docker `mercure_data`. PHP ne fait pas confiance à cette CA par défaut, ce
qui casse le publish (`unable to get local issuer certificate`) tant qu'on
ne lui donne pas explicitement le certificat root à faire confiance.

### Régénérer les certificats (si le volume `mercure_data` a été supprimé, ex: `docker compose down -v`)

1. Redémarrer le hub :
   ```bash
   docker compose up
   ```
   Caddy régénère une nouvelle CA racine dans `mercure_data` au premier démarrage.

2. Extraire le nouveau certificat root du conteneur :
   ```bash
   docker compose cp mercure_hub:/data/caddy/pki/authorities/local/root.crt ./mercure-root-ca.crt
   ```

3. Reconstruire le bundle CA combiné (CA système + CA Caddy) utilisé par PHP :
   ```bash
   cat /opt/homebrew/etc/openssl@3/cert.pem ./mercure-root-ca.crt > /opt/homebrew/etc/php/8.5/combined-ca-bundle.pem
   ```
   `php.ini` (`curl.cainfo` et `openssl.cafile`) pointe déjà vers ce fichier — pas besoin
   de le retoucher, juste de le régénérer avec la nouvelle CA.

4. Redémarrer `symfony server:start` pour que PHP-FPM relise le nouveau bundle.

5. (Pour tester sur téléphone) réinstaller la CA sur le téléphone :
   ```bash
   adb push ./mercure-root-ca.crt /sdcard/Download/mercure-root-ca.crt
   ```
   Puis dans le téléphone : Paramètres > Sécurité > Chiffrement et identifiants >
   Installer un certificat > Certificat CA.

   Sur iPhone : envoyer le fichier au téléphone (AirDrop/mail), Réglages >
   Général > VPN et gestion de l'appareil > installer le profil, **puis**
   Réglages > Général > À propos > Réglages de confiance des certificats >
   activer la confiance totale pour cette CA (étape facile à oublier — sans
   elle le profil est installé mais pas utilisé).

### Vérifier que ce n'est pas plutôt un souci de JWT

Le hub valide les JWT signés avec `MERCURE_JWT_SECRET`. Cette valeur doit être
**identique** des deux côtés :
- `.env` (racine, committé) → lu par `docker compose` pour `MERCURE_PUBLISHER_JWT_KEY`/`MERCURE_SUBSCRIBER_JWT_KEY`
- `.env.local` (gitignored) → lu par Symfony pour signer les JWT publiés

Si les deux valeurs diffèrent, le publish échoue avec une erreur **401**, pas
une erreur TLS (`unable to get local issuer certificate`). Se fier au message
d'erreur exact (loggé via `MercurePublisherService`) pour savoir lequel des
deux problèmes on a réellement en face.
