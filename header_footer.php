  <?php
  $Css_Js_Meta = new Css_Js_Meta( [ "/js/scripts.js" ] );
  echo $Css_Js_Meta->merge();
  ?>

  <script src="/js/fancybox/fancybox.umd.js"></script>
  <script>
  document.addEventListener("DOMContentLoaded", function () {
    Fancybox.bind('[data-fancybox="invoice-products"]', {
      groupAttr: false,
      fadeEffect: false,
      zoomEffect: false,
      showClass: false,
      hideClass: false,
      dragToClose: true,
      placeFocusBack: false
    });
  });
  </script>

</body>
</html>
