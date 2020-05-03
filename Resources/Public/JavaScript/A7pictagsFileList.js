define(['ndarray', 'TYPO3/CMS/A7neuralnet/NeuralNetworkManager'], function (ndarray, nnm) {
  const net = nnm.get('a7pictags');
  class Tagger {
    constructor(url, uid) {
      this.url = url;
      this.uid = uid;
    }
    execute() {
      return net.evaluateImage(this.url)
        .then(result => {
          let values = Object.entries(result);
          values = values.filter(([_, value]) => value >= 0.5);
          values = values.map(([label, _]) => label);
          const formData = new FormData();
          formData.set('tags', JSON.stringify(values));
          formData.set('fileUid', this.uid);
          return formData;
        }, error => `Unable to evaluate neural network. ${error}`)
        .then(formData => fetch(TYPO3.settings.ajaxUrls['a7pictags-tag-single'], {
          method: 'POST',
          cache: 'no-cache',
          redirect: 'follow',
          body: formData,
        }))
        .then(response => response.json(), error => `Unable to actually set tags via AJAX. ${error}`)
        .then(response => response.tags);
    }
  }
  function init() {
    const buttons = document.querySelectorAll('.a7pictags-target.btn');
    for (const button of buttons) {
      const info = JSON.parse(button.dataset.info);
      const tagger = new Tagger(info.url, info.uid);
      button.addEventListener('click', () => {
        if (button.classList.contains('loading')) {
          return;
        }
        button.classList.add('loading');
        tagger.execute().then(tags => {
          top.TYPO3.Notification.success(`Auto tagging done!`, `Attached tags ${tags.join(', ')} to ${info.url}.`, 3);
          button.classList.remove('loading');
        }, error => {
          top.TYPO3.Notification.error(`Error with auto tagging`, `Cannot append tags to image ${info.url}. ${error}`, 5);
          button.classList.remove('loading');
        });
      });
    }
  }
  if (document.readyState !== 'loading')
    init();
  else
    document.addEventListener('DOMContentLoaded', init);
});
