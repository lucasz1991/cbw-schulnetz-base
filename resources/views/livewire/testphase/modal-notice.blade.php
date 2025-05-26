
<div>

    <!-- Such-Modal -->
    <div x-data="{ open: @entangle('showPreflightModal') }"
         x-show="open"
         x-transition.opacity
          x-trap.inert.noscroll="open"
         x-cloak
         class="fixed inset-0 bg-black/50 z-50 p-4 overflow-y-auto overflow-x-hidden">
         <div  class="flex items-center justify-center ">
             <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 m-4">
                  <div name="title" class="border-b mb-4">
                     <h3 class="font-bold ">Willkommen zu RegulierungsCheck</h3>
                 </div>
     
                 <div name="content">
                     <p class="text-gray-600 leading-relaxed">
                         RegulierungsCheck ist eine Plattform zur Bewertung und Analyse von Schadenregulierungen durch Versicherungen. Sie befindet sich derzeit in einer <strong>Testphase</strong>, in der alle Funktionen ausprobiert und erste Rückmeldungen gesammelt werden sollen.<br><br>
                         Bitte verwenden Sie möglichst <strong>Testdaten / keine echten Fälle</strong>, da noch nicht alle Prozesse final sind. Ihr Feedback ist sehr wertvoll und hilft dabei, die Plattform weiter zu verbessern.<br><br>
                         Nach dem Test können Sie uns Ihre Eindrücke gerne über die <a href="/contact" class="text-blue-600 hover:underline">Kontaktseite</a> mitteilen.
                     </p>
                 </div>
     
     
                 <div name="footer" class="flex  justify-between content-center mt-4">
                     <x-buttons.button-basic wire:click="hide">
                         Verstanden
                     </x-buttons.button-basic>
                         <x-buttons.button-basic  href="/contact" class="ml-3">
                             Feedback
                         </x-buttons.button-basic>
                 </div>
             </div>
         </div>
    </div>
</div>
