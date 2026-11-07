// Éditeur à blocs des pages : Editor.js (embarqué) + deux outils maison : image et bloc dynamique
'use strict';

(function () {
  const holder = document.getElementById('editeur');
  if (!holder || typeof EditorJS === 'undefined') { return; }
  const form = document.getElementById('formulaire-page');
  const field = document.getElementById('champ-blocs');
  const csrf = form.querySelector('input[name="_csrf"]').value;
  const dynamiques = JSON.parse(holder.dataset.blocsDynamiques);

  // Image : envoyée au serveur, qui la ré-encode (métadonnées supprimées) et renvoie son chemin
  class ImageBoussole {
    static get toolbox() { return { title: 'Image', icon: '🖼' }; }
    constructor({ data }) { this.data = data || {}; }
    render() {
      const box = document.createElement('div');
      box.className = 'bloc-image';
      const preview = document.createElement('img');
      const caption = document.createElement('input');
      const alt = document.createElement('input');
      caption.placeholder = 'Légende (facultative)';
      alt.placeholder = "Description de l'image pour les personnes qui ne la voient pas";
      caption.value = this.data.legende || '';
      alt.value = this.data.alt || '';
      this.caption = caption;
      this.alt = alt;
      if (this.data.chemin) { preview.src = holder.dataset.envoiImage.replace(/admin\/images$/, '') + this.data.chemin; }
      const input = document.createElement('input');
      input.type = 'file';
      input.accept = 'image/jpeg,image/png,image/webp,image/gif';
      input.addEventListener('change', () => {
        const body = new FormData();
        body.append('_csrf', csrf);
        body.append('image', input.files[0]);
        fetch(holder.dataset.envoiImage, { method: 'POST', body: body, credentials: 'same-origin' })
          .then((response) => response.json())
          .then((result) => {
            if (result.success) { this.data.chemin = result.chemin; preview.src = result.url; } else { window.alert(result.erreur); }
          });
      });
      box.append(this.data.chemin ? preview : input, alt, caption);
      if (!this.data.chemin) { box.prepend(preview); }
      return box;
    }
    save() { return { chemin: this.data.chemin || '', legende: this.caption.value, alt: this.alt.value }; }
  }

  // Bloc dynamique : une liste courte et fixe (événements, espaces publics…)
  class BlocBoussole {
    static get toolbox() { return { title: 'Bloc dynamique', icon: '⚙' }; }
    constructor({ data }) { this.data = data || {}; }
    render() {
      const select = document.createElement('select');
      Object.entries(dynamiques).forEach(([key, label]) => {
        const option = document.createElement('option');
        option.value = key;
        option.textContent = label;
        option.selected = key === this.data.bloc;
        select.append(option);
      });
      this.select = select;
      const box = document.createElement('div');
      box.className = 'bloc-dynamique';
      box.append('Bloc dynamique : ', select);
      return box;
    }
    save() { return { bloc: this.select.value }; }
  }

  const icones = JSON.parse(holder.dataset.icones || '{}');
  const champ = function (valeur, aide) {
    const input = document.createElement('input');
    input.value = valeur || '';
    input.placeholder = aide;
    input.className = 'champ-bloc';
    return input;
  };

  // Liste d'éléments répétables (cartes, chiffres) : une ligne par élément, « + » pour en ajouter
  class Repetable {
    constructor({ data }) { this.data = data || {}; this.lignes = []; }
    render() {
      this.box = document.createElement('div');
      this.box.className = 'bloc-repetable';
      const titre = document.createElement('p');
      titre.className = 'bloc-repetable-titre';
      titre.textContent = this.constructor.libelle;
      this.box.append(titre);
      this.liste = document.createElement('div');
      this.box.append(this.liste);
      (this.data.items && this.data.items.length ? this.data.items : [{}]).forEach((item) => this.ajouter(item));
      const plus = document.createElement('button');
      plus.type = 'button';
      plus.className = 'lien-bouton';
      plus.textContent = '+ ajouter';
      plus.addEventListener('click', () => this.ajouter({}));
      this.box.append(plus);
      return this.box;
    }
    ajouter(item) {
      const ligne = document.createElement('div');
      ligne.className = 'bloc-ligne';
      const champs = this.champs(item);
      Object.values(champs).forEach((c) => ligne.append(c));
      const moins = document.createElement('button');
      moins.type = 'button';
      moins.className = 'lien-bouton';
      moins.textContent = 'retirer';
      moins.addEventListener('click', () => { ligne.remove(); this.lignes = this.lignes.filter((l) => l.champs !== champs); });
      ligne.append(moins);
      this.liste.append(ligne);
      this.lignes.push({ champs: champs });
    }
    save() { return { items: this.lignes.map((l) => Object.fromEntries(Object.entries(l.champs).map(([k, c]) => [k, c.value]))) }; }
  }

  class Cartes extends Repetable {
    static get toolbox() { return { title: 'Cartes', icon: '▦' }; }
    static get libelle() { return 'Cartes : icône, titre, texte court, lien (facultatif)'; }
    champs(item) {
      const icone = document.createElement('select');
      Object.entries(icones).forEach(([k, v]) => { const o = document.createElement('option'); o.value = k; o.textContent = v; o.selected = k === (item.icone || ''); icone.append(o); });
      return { icone: icone, titre: champ(item.titre, 'Titre'), texte: champ(item.texte, 'Texte court'), lien: champ(item.lien, 'Lien : /page/… ou https://…') };
    }
  }

  class Chiffres extends Repetable {
    static get toolbox() { return { title: 'Chiffres clés', icon: '#' }; }
    static get libelle() { return 'Chiffres clés : valeur et libellé (6 au plus)'; }
    champs(item) { return { valeur: champ(item.valeur, 'Valeur (ex. 236)'), libelle: champ(item.libelle, 'Libellé (ex. cadets)') }; }
  }

  class Bouton {
    static get toolbox() { return { title: 'Bouton', icon: '▭' }; }
    constructor({ data }) { this.data = data || {}; }
    render() {
      const box = document.createElement('div');
      box.className = 'bloc-repetable';
      this.texte = champ(this.data.texte, 'Texte du bouton');
      this.lien = champ(this.data.lien, "Lien : /page/…, https://…, ou « adhesion » pour le lien d'adhésion");
      this.style = document.createElement('select');
      [['principal', 'plein'], ['secondaire', 'contour']].forEach(([k, v]) => { const o = document.createElement('option'); o.value = k; o.textContent = v; o.selected = k === (this.data.style || 'principal'); this.style.append(o); });
      box.append(this.texte, this.lien, this.style);
      return box;
    }
    save() { return { texte: this.texte.value, lien: this.lien.value, style: this.style.value }; }
  }

  const editor = new EditorJS({
    holder: holder,
    data: JSON.parse(holder.dataset.blocs),
    placeholder: 'Écrivez ici…',
    tools: {
      header: { class: Header, config: { levels: [2, 3, 4], defaultLevel: 2 } },
      list: { class: EditorjsList, inlineToolbar: true },
      quote: { class: Quote, inlineToolbar: true },
      delimiter: Delimiter,
      image: ImageBoussole,
      boussole: BlocBoussole,
      cartes: Cartes,
      chiffres: Chiffres,
      bouton: Bouton,
    },
  });

  let saving = false;
  form.addEventListener('submit', function (event) {
    if (saving) { return; }
    event.preventDefault();
    editor.save().then(function (output) {
      field.value = JSON.stringify(output);
      saving = true;
      form.submit();
    });
  });
})();
