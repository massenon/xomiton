# Règles R8 de la version release de Xomiton.
# Flutter, Firebase et les plugins fournissent leurs propres règles « consumer » ;
# on ne garde ici que ce qui est propre à l'application.

# Points d'entrée du code natif appelé par canal Flutter (MainActivity : FLAG_SECURE, mode discret).
-keep class bj.xomiton.xomiton.MainActivity { *; }

# Flutter : le moteur peut citer des classes Play Core absentes (composants différés non utilisés).
-dontwarn com.google.android.play.core.**

# Pas d'obfuscation des noms de ressources utilisés par les alias de lanceur (mode discret).
-keepclassmembers class **.R$* { public static <fields>; }

# Traces d'erreur lisibles (le fichier de correspondance est produit par le build).
-keepattributes SourceFile,LineNumberTable
-renamesourcefileattribute SourceFile
