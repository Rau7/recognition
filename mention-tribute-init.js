// Tribute.js mention entegrasyonu
// AJAX ile kullanıcı arama örneği de eklenmiştir.

document.addEventListener("DOMContentLoaded", function () {
  var tribute = new Tribute({
    values: function (text, cb) {
      // AJAX ile kullanıcı arama
      fetch(M.cfg.wwwroot + "/local/recognition/ajax.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body:
          "action=searchusers&query=" +
          encodeURIComponent(text) +
          "&sesskey=" +
          encodeURIComponent(M.cfg.sesskey),
      })
        .then(function (resp) {
          return resp.json();
        })
        .then(function (data) {
          if (data.success) {
            cb(
              data.data.map(function (u) {
                return { key: u.fullname, value: u.id };
              })
            );
          } else {
            cb([]);
          }
        });
    },
    selectTemplate: function (item) {
      // Mention'dan sonra &nbsp; ve zero-width space ekle
      return (
        '<span class="mention-highlight" data-userid="' +
        item.original.value +
        '">@' +
        item.original.key +
        "</span>&nbsp;\u200B"
      );
    },
    menuItemTemplate: function (item) {
      return (
        '<span class="mention-highlight">@' + item.string + "&nbsp;</span>"
      );
    },
  });
  var editor = document.getElementById("mention-editor");
  if (editor) tribute.attach(editor);

  // Form submitte içeriği gizli inputa aktar
  if (editor) {
    var form = editor.closest("form");
    // Her input değişikliğinde de güncelle
    editor.addEventListener("input", function () {
      document.getElementById("message-hidden").value = editor.innerHTML;
      console.log(
        "[mention] input event: message-hidden güncellendi:",
        editor.innerHTML
      );
    });
    if (form) {
      form.addEventListener("submit", function () {
        document.getElementById("message-hidden").value = editor.innerHTML;
        console.log(
          "[mention] submit event: message-hidden güncellendi:",
          editor.innerHTML
        );
      });
    }
  }

  // Yorum inputlarına da Tribute ekle
  function attachTributeToComments() {
    document
      .querySelectorAll(".comment-input[contenteditable]")
      .forEach(function (input) {
        if (!input.hasAttribute("data-tribute-attached")) {
          tribute.attach(input);
          input.setAttribute("data-tribute-attached", "true");
        }
      });
  }
  attachTributeToComments();

  // Dinamik olarak eklenen yorum inputlarını da izle
  var observer = new MutationObserver(function (mutations) {
    mutations.forEach(function (mutation) {
      if (mutation.addedNodes) {
        mutation.addedNodes.forEach(function (node) {
          if (node.nodeType === 1) {
            if (
              node.matches &&
              node.matches(".comment-input[contenteditable]")
            ) {
              tribute.attach(node);
              node.setAttribute("data-tribute-attached", "true");
            }
            // Eğer form içinde ise
            node.querySelectorAll &&
              node
                .querySelectorAll(".comment-input[contenteditable]")
                .forEach(function (input) {
                  tribute.attach(input);
                  input.setAttribute("data-tribute-attached", "true");
                });
          }
        });
      }
    });
  });
  observer.observe(document.body, { childList: true, subtree: true });

  // Form submitte içeriği gizli inputa aktar
  var form = editor.closest("form");
  if (form) {
    form.addEventListener("submit", function () {
      document.getElementById("message-hidden").value = editor.innerHTML;
    });
  }
});
