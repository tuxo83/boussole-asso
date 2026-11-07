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
